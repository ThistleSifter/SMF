<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Search\SearchApi;
use SMF\Search\SearchResult;
use SMF\Security;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * Shows the search form.
 */
class Search2 implements ActionInterface
{
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The number of results found.
	 */
	public int $num_results = 0;

	/**
	 * @var array
	 *
	 * ID numbers of messages in the search results.
	 */
	public array $messages = [];

	/**
	 * @var array
	 *
	 * ID numbers of the authors of the $messages.
	 */
	public array $posters = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Performs the search and shows the results.
	 *
	 * @todo Break this up into separate protected functions.
	 */
	public function execute(): void
	{
		$this->redirectToMemberSearch();
		$this->checkLoadAverage();
		$this->preventPrefetch();

		// Are you allowed?
		User::$me->isAllowedTo('search_posts');

		// Load up the search API we are going to use.
		SearchApi::load();
		SearchApi::$loadedApi->initializeSearch();

		Utils::$context['search_errors'] = SearchApi::$loadedApi->errors;

		$this->setupVerification();

		// Did we encounter any errors?
		if ($this->errorCheck()) {
			return;
		}

		// Spam me not, Spam-a-lot?
		$this->spamCheck();

		// For backward compatibility reasons, this has to be defined before calling searchQuery.
		// @todo The name of this $context var is really misleading.
		Utils::$context['topics'] = &SearchApi::$loadedApi->results;

		// Perform the main search query.
		// All of these arguments are deprecated.
		// Mods should rewrite their code to use object properties directly.
		SearchApi::$loadedApi->searchQuery(
			SearchApi::$loadedApi->getQueryParams(),
			SearchApi::$loadedApi->searchWords,
			SearchApi::$loadedApi->excludedIndexWords,
			SearchApi::$loadedApi->participants,
			SearchApi::$loadedApi->searchArray,
		);

		$this->initSearchContext();
		$this->setupTemplate();
	}

	/**
	 * Callback to return messages - saves memory.
	 *
	 * What it does:
	 * - callback function for the results sub template.
	 * - loads the necessary contextual data to show a search result.
	 *
	 * @param bool $reset Whether to reset the counter
	 * @return false|array An array of contextual info related to this search
	 */
	public function prepareSearchContext(bool $reset = false): bool|array
	{
		static $recycle_board = null;
		static $counter = null;

		if ($recycle_board === null) {
			$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;
		}

		// Remember which message this is.  (ie. reply #83)
		if ($counter == null || $reset) {
			$counter = ((int) $_REQUEST['start']) + 1;
		}

		// Start from the beginning...
		if ($reset) {
			return @Db::$db->data_seek(SearchResult::$getter->reset(), 0);
		}

		if (!isset(SearchResult::$getter)) {
			return false;
		}

		$message = SearchResult::$getter->current();
		SearchResult::$getter->next();

		if (!$message) {
			return false;
		}

		$output = $message->format($counter);

		if (!empty(Theme::$current->options['display_quick_mod'])) {
			$started = $output['first_post']['member']['id'] == User::$me->id;

			$output['quick_mod'] = [
				'lock' => in_array(0, SearchResult::$boards_can['lock_any']) || in_array($output['board']['id'], SearchResult::$boards_can['lock_any']) || ($started && (in_array(0, SearchResult::$boards_can['lock_own']) || in_array($output['board']['id'], SearchResult::$boards_can['lock_own']))),
				'sticky' => (in_array(0, SearchResult::$boards_can['make_sticky']) || in_array($output['board']['id'], SearchResult::$boards_can['make_sticky'])),
				'move' => in_array(0, SearchResult::$boards_can['move_any']) || in_array($output['board']['id'], SearchResult::$boards_can['move_any']) || ($started && (in_array(0, SearchResult::$boards_can['move_own']) || in_array($output['board']['id'], SearchResult::$boards_can['move_own']))),
				'remove' => in_array(0, SearchResult::$boards_can['remove_any']) || in_array($output['board']['id'], SearchResult::$boards_can['remove_any']) || ($started && (in_array(0, SearchResult::$boards_can['remove_own']) || in_array($output['board']['id'], SearchResult::$boards_can['remove_own']))),
				'restore' => Utils::$context['can_restore_perm'] && (Config::$modSettings['recycle_board'] == $output['board']['id']),
			];

			Utils::$context['can_lock'] |= $output['quick_mod']['lock'];
			Utils::$context['can_sticky'] |= $output['quick_mod']['sticky'];
			Utils::$context['can_move'] |= $output['quick_mod']['move'];
			Utils::$context['can_remove'] |= $output['quick_mod']['remove'];
			Utils::$context['can_merge'] |= in_array($output['board']['id'], SearchResult::$boards_can['merge_any']);
			Utils::$context['can_restore'] |= $output['quick_mod']['restore'];
			Utils::$context['can_markread'] = User::$me->is_logged;

			// Sets Utils::$context['qmod_actions']
			// This is also where the integrate_quick_mod_actions_search hook now lives.
			QuickModeration::getActions(true);
		}

		$counter++;

		IntegrationHook::call('integrate_search_message_context', [&$output, &$message, $counter]);

		return $output;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Maximum length of the string.
		Utils::$context['search_string_limit'] = SearchApi::MAX_LENGTH;

		// Number of pages hard maximum - normally not set at all.
		Config::$modSettings['search_max_results'] = empty(Config::$modSettings['search_max_results']) ? 200 * Config::$modSettings['search_results_per_page'] : (int) Config::$modSettings['search_max_results'];

		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] - ((int) $_REQUEST['start'] % Config::$modSettings['search_results_per_page']) : 0;

		Lang::load('Search');

		Utils::$context['robot_no_index'] = true;
	}

	/**
	 * If coming from the quick search box and trying to search on members,
	 * redirect to the right place for that.
	 */
	protected function redirectToMemberSearch(): void
	{
		if (isset($_REQUEST['search_selection']) && $_REQUEST['search_selection'] === 'members') {
			Utils::redirectexit(Config::$scripturl . '?action=mlist;sa=search;fields=name,email;search=' . urlencode($_REQUEST['search']));
		}
	}

	/**
	 * Aborts search if the load average is too high right now.
	 */
	protected function checkLoadAverage(): void
	{
		if (!empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_search']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_search']) {
			ErrorHandler::fatalLang('loadavg_search_disabled', false);
		}
	}

	/**
	 * Blocks browser attempts to prefetch the topic display.
	 */
	protected function preventPrefetch(): void
	{
		// No, no, no... this is a bit hard on the server, so don't you go prefetching it!
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
			ob_end_clean();
			Utils::sendHttpStatus(403);

			die;
		}
	}

	/**
	 * Checks for errors and shows the initial search page if there were.
	 */
	protected function errorCheck(): bool
	{
		IntegrationHook::call('integrate_search_errors');

		// One or more search errors? Go back to the first search screen.
		if (!empty(Utils::$context['search_errors'])) {
			$_REQUEST['params'] = SearchApi::$loadedApi->compressParams();
			Search::call();

			return true;
		}

		return false;
	}

	/**
	 * Block spam attempts, but without driving guests completely crazy.
	 */
	protected function spamCheck(): void
	{
		if (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != SearchApi::$loadedApi->params['search']) {
			Security::spamProtection('search');
		}

		// Store the last search string to allow pages of results to be browsed.
		$_SESSION['last_ss'] = SearchApi::$loadedApi->params['search'];
	}

	/**
	 * Loads the template and sets some related contextual info.
	 */
	protected function setupTemplate(): void
	{
		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('Search');
		}

		// If we're doing XML we need to use the results template regardless really.
		Utils::$context['sub_template'] = 'results';

		Utils::$context['compact'] = !SearchApi::$loadedApi->params['show_complete'];

		// Remember current sort type and sort direction
		Utils::$context['current_sorting'] = SearchApi::$loadedApi->params['sort'] . '|' . SearchApi::$loadedApi->params['sort_dir'];

		Utils::$context['search_ignored'] = &SearchApi::$loadedApi->ignored;
		Utils::$context['mark'] = &SearchApi::$loadedApi->marked;

		// Let the user adjust the search query, should they wish?
		Utils::$context['search_params'] = SearchApi::$loadedApi->params;

		if (isset(Utils::$context['search_params']['search'])) {
			Utils::$context['search_params']['search'] = Utils::htmlspecialchars(Utils::$context['search_params']['search']);
		}

		if (isset(Utils::$context['search_params']['userspec'])) {
			Utils::$context['search_params']['userspec'] = Utils::htmlspecialchars(Utils::$context['search_params']['userspec']);
		}

		Utils::$context['params'] = SearchApi::$loadedApi->compressParams();

		// ... and add the links to the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=search;params=' . SearchApi::$loadedApi->compressParams(),
			'name' => Lang::$txt['search'],
		];
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=search2;params=' . SearchApi::$loadedApi->compressParams(),
			'name' => Lang::$txt['search_results'],
		];

		// Now that we know how many results to expect we can start calculating the page numbers.
		$start = (int) $_REQUEST['start'];
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=search2;params=' . SearchApi::$loadedApi->compressParams(), $start, $this->num_results, (int) Config::$modSettings['search_results_per_page'], false);

		Utils::$context['key_words'] = SearchApi::$loadedApi->searchArray;

		// Setup the default topic icons... for checking they exist and the like!
		Utils::$context['icon_sources'] = [];

		foreach (Utils::$context['stable_icons'] as $icon) {
			Utils::$context['icon_sources'][$icon] = 'images_url';
		}

		Utils::$context['page_title'] = Lang::$txt['search_results'];
		Utils::$context['get_topics'] = [$this, 'prepareSearchContext'];
		Utils::$context['can_restore_perm'] = User::$me->allowedTo('move_any') && !empty(Config::$modSettings['recycle_enable']);
		Utils::$context['can_restore'] = false; // We won't know until we handle the context later whether we can actually restore...

		Utils::$context['jump_to'] = [
			'label' => addslashes(Utils::htmlspecialcharsDecode(Lang::$txt['jump_to'])),
			'board_name' => addslashes(Utils::htmlspecialcharsDecode(Lang::$txt['select_destination'])),
		];

		// Define the sort order options.
		Utils::$context['sort_options'] = [
			'relevance|desc' => [
				'label' => 'search_orderby_relevant_first',
				'value' => 'relevance|desc',
				'selected' => Utils::$context['current_sorting'] == 'relevance|desc',
			],
			'num_replies|desc' => [
				'label' => 'search_orderby_large_first',
				'value' => 'num_replies|desc',
				'selected' => Utils::$context['current_sorting'] == 'num_replies|desc',
			],
			'num_replies|asc' => [
				'label' => 'search_orderby_small_first',
				'value' => 'num_replies|asc',
				'selected' => Utils::$context['current_sorting'] == 'num_replies|asc',
			],
			'id_msg|desc' => [
				'label' => 'search_orderby_recent_first',
				'value' => 'id_msg|desc',
				'selected' => Utils::$context['current_sorting'] == 'id_msg|desc',
			],
			'id_msg|asc' => [
				'label' => 'search_orderby_old_first',
				'value' => 'id_msg|asc',
				'selected' => Utils::$context['current_sorting'] == 'id_msg|asc',
			],
		];

		// Define the hidden inputs that let the user adjust the search.
		Utils::$context['hidden_inputs'] = [
			'searchtype' => '<input type="hidden" name="searchtype" value="' . (!empty(Utils::$context['search_params']['searchtype']) ? Utils::$context['search_params']['searchtype'] : 0) . '">',
			'userspec' => '<input type="hidden" name="userspec" value="' . (!empty(Utils::$context['search_params']['userspec']) ? Utils::$context['search_params']['userspec'] : '') . '">',
			'show_complete' => '<input type="hidden" name="show_complete" value="' . (!empty(Utils::$context['search_params']['show_complete']) ? 1 : 0) . '">',
			'subject_only' => '<input type="hidden" name="subject_only" value="' . (!empty(Utils::$context['search_params']['subject_only']) ? 1 : 0) . '">',
			'minage' => '<input type="hidden" name="minage" value="' . (!empty(Utils::$context['search_params']['minage']) ? Utils::$context['search_params']['minage'] : '0') . '">',
			'maxage' => '<input type="hidden" name="maxage" value="' . (!empty(Utils::$context['search_params']['maxage']) ? Utils::$context['search_params']['maxage'] : '9999') . '">',
			'sort' => '<input type="hidden" name="sort" value="' . (!empty(Utils::$context['search_params']['sort']) ? Utils::$context['search_params']['sort'] : 'relevance') . '">',
		];

		if (!empty(Utils::$context['search_params']['brd'])) {
			foreach (Utils::$context['search_params']['brd'] as $board_id) {
				Utils::$context['hidden_inputs']['brd_' . $board_id] = '<input type="hidden" name="brd[' . $board_id . ']" value="' . $board_id . '">';
			}
		}

		SearchApi::$loadedApi->resultsContext();
	}

	/**
	 * Sets up anti-spam verification stuff, if needed.
	 */
	protected function setupVerification(): void
	{
		// Do we have captcha enabled?
		if (User::$me->is_guest && !empty(Config::$modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != SearchApi::$loadedApi->params['search'])) {
			$verifier = new Verifier(['id' => 'search']);

			if (!empty($verifier->errors)) {
				foreach ($verifier->errors as $error) {
					Utils::$context['search_errors'][$error] = true;
				}
			}
			// Don't keep asking for it - they've proven themselves worthy.
			else {
				$_SESSION['ss_vv_passed'] = true;
			}
		}
	}

	/**
	 * Populates $this->posters with IDs of authors of the posts we will show.
	 */
	protected function getPosters(): void
	{
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}messages
			WHERE id_member != {int:no_member}
				AND id_msg IN ({array_int:message_list})
			LIMIT {int:limit}',
			[
				'message_list' => $this->messages,
				'no_member' => 0,
				'limit' => count(SearchApi::$loadedApi->results),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->posters[] = $row['id_member'];
		}
		Db::$db->free_result($request);
	}

	/**
	 * Initializes stuff we need to display the search results.
	 */
	protected function initSearchContext(): void
	{
		if (empty(SearchApi::$loadedApi->results)) {
			return;
		}

		SearchResult::setBoardsCan();

		// What messages are we using?
		$this->messages = array_map('intval', array_keys(SearchApi::$loadedApi->results));

		// Load the posters...
		$this->getPosters();

		IntegrationHook::call('integrate_search_message_list', [&$this->messages, &$this->posters]);

		if (!empty($this->posters)) {
			User::load(array_unique($this->posters));
		}

		SearchResult::$getter = SearchResult::get($this->messages);

		// How many results will the user be able to see?
		$this->num_results = !empty($_SESSION['search_cache']['num_results']) ? $_SESSION['search_cache']['num_results'] : SearchResult::getNumResults();

		// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
		if ($this->num_results == 0) {
			SearchApi::$loadedApi->results = [];

			return;
		}

		SearchApi::$loadedApi->setParticipants();
	}
}

?>