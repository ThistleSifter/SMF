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

namespace SMF;

/**
 * Class BrowserDetector
 *  This class is an experiment for the job of correctly detecting browsers and settings needed for them.
 * - Detects the following browsers
 * - Opera, Webkit, Firefox, Web_tv, Konqueror, IE, Gecko
 * - Webkit variants: Chrome, iphone, blackberry, android, safari, ipad, ipod
 * - Opera Versions: 6, 7, 8 ... 10 ... and mobile mini and mobi
 * - Firefox Versions: 1, 2, 3 .... 11 ...
 * - Chrome Versions: 1 ... 18 ...
 * - IE Versions: 4, 5, 5.5, 6, 7, 8, 9, 10 ... mobile and Mac
 * - MS Edge
 * - Nokia
 */
class BrowserDetector
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Holds all the browser information.
	 * Its contents will be placed into Utils::$context['browser']
	 */
	private array $_browsers = [];

	/**
	 * @var bool
	 *
	 * Whether or not this might be a mobile device
	 */
	private bool $_is_mobile = false;

	/**
	 * An instance of this class.
	 */
	protected static $obj;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience method.
	 */
	public static function call()
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		self::$obj->detectBrowser();
	}

	/**
	 * Are we using this browser?
	 *
	 * @param string $browser The browser we are checking for.
	 * @return bool Whether or not the current browser is what we're looking for.
	 */
	public static function isBrowser(string $browser): bool
	{
		// Don't know any browser!
		if (!isset(self::$obj) || empty(self::$obj->_browsers)) {
			self::call();
		}

		return !empty(self::$obj->_browsers[$browser]) || !empty(self::$obj->_browsers['is_' . $browser]);
	}

	/****************
	 * Public methods
	 ****************/

	/**
	 * The main method of this class, you know the one that does the job: detect the thing.
	 *  - determines the user agent (browser) as best it can.
	 */
	public function detectBrowser(): void
	{
		// Initialize some values we'll set differently if necessary...
		$this->_browsers['needs_size_fix'] = false;

		// One at a time, one at a time, and in this order too
		if ($this->isOpera()) {
			$this->setupOpera();
		}
		// Meh...
		elseif ($this->isEdge()) {
			$this->setupEdge();
		}
		// Them webkits need to be set up too
		elseif ($this->isWebkit()) {
			$this->setupWebkit();
		}
		// We may have work to do on Firefox...
		elseif ($this->isFirefox()) {
			$this->setupFirefox();
		}
		// Old friend, old frenemy
		elseif ($this->isIe()) {
			$this->setupIe();
		}

		// Just a few mobile checks
		$this->isOperaMini();
		$this->isOperaMobi();

		// IE11 seems to be fine by itself without being lumped into the "is_ie" category
		$this->isIe11();

		// Be you robot or human?
		if (User::$me->possibly_robot) {
			// This isn't meant to be reliable, it's just meant to catch most bots to prevent PHPSESSID from showing up.
			$this->_browsers['possibly_robot'] = !empty(User::$me->possibly_robot);

			// Robots shouldn't be logging in or registering.  So, they aren't a bot.  Better to be wrong than sorry (or people won't be able to log in!), anyway.
			if ((isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['login', 'login2', 'register', 'signup'])) || !User::$me->is_guest) {
				$this->_browsers['possibly_robot'] = false;
			}
		} else {
			$this->_browsers['possibly_robot'] = false;
		}

		// Fill out the historical array as needed to support old mods that don't use isBrowser
		$this->fillInformation();

		// Make it easy to check if the browser is on a mobile device.
		$this->_browsers['is_mobile'] = $this->_is_mobile;

		// Last step ...
		$this->setupBrowserPriority();

		// Now see what you've done!
		Utils::$context['browser'] = $this->_browsers;
	}

	/**
	 * Determine if the browser is Opera or not
	 *
	 * @return bool Whether or not this is Opera
	 */
	public function isOpera(): bool
	{
		if (!isset($this->_browsers['is_opera'])) {
			$this->_browsers['is_opera'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'Opera');
		}

		return $this->_browsers['is_opera'];
	}

	/**
	 * Determine if the browser is IE or not
	 *
	 * @return bool true Whether or not the browser is IE
	 */
	public function isIe(): bool
	{
		// I'm IE, Yes I'm the real IE; All you other IEs are just imitating.
		if (!isset($this->_browsers['is_ie'])) {
			$this->_browsers['is_ie'] = !$this->isOpera() && !$this->isGecko() && !$this->isWebTv() && preg_match('~MSIE \d+~', $_SERVER['HTTP_USER_AGENT']) === 1;
		}

		return $this->_browsers['is_ie'];
	}

	/**
	 * Determine if the browser is IE11 or not
	 *
	 * @return bool Whether or not the browser is IE11
	 */
	public function isIe11(): bool
	{
		// IE11 is a bit different than earlier versions
		// The isGecko() part is to ensure we get this right...
		if (!isset($this->_browsers['is_ie11'])) {
			$this->_browsers['is_ie11'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'Trident') && $this->isGecko();
		}

		return $this->_browsers['is_ie11'];
	}

	/**
	 * Determine if the browser is Edge or not
	 *
	 * @return bool Whether or not the browser is Edge
	 */
	public function isEdge(): bool
	{
		if (!isset($this->_browsers['is_edge'])) {
			$this->_browsers['is_edge'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'Edge');
		}

		return $this->_browsers['is_edge'];
	}

	/**
	 * Determine if the browser is a Webkit based one or not
	 *
	 * @return bool Whether or not this is a Webkit-based browser
	 */
	public function isWebkit(): bool
	{
		if (!isset($this->_browsers['is_webkit'])) {
			$this->_browsers['is_webkit'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'AppleWebKit');
		}

		return $this->_browsers['is_webkit'];
	}

	/**
	 * Determine if the browser is Firefox or one of its variants
	 *
	 * @return bool Whether or not this is Firefox (or one of its variants)
	 */
	public function isFirefox(): bool
	{
		if (!isset($this->_browsers['is_firefox'])) {
			$this->_browsers['is_firefox'] = preg_match('~(?:Firefox|Ice[wW]easel|IceCat|Shiretoko|Minefield)/~', $_SERVER['HTTP_USER_AGENT']) === 1 && $this->isGecko();
		}

		return $this->_browsers['is_firefox'];
	}

	/**
	 * Determine if the browser is WebTv or not
	 *
	 * @return bool Whether or not this is WebTV
	 */
	public function isWebTv(): bool
	{
		if (!isset($this->_browsers['is_web_tv'])) {
			$this->_browsers['is_web_tv'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'WebTV');
		}

		return $this->_browsers['is_web_tv'];
	}

	/**
	 * Determine if the browser is konqueror or not
	 *
	 * @return bool Whether or not this is Konqueror
	 */
	public function isKonqueror(): bool
	{
		if (!isset($this->_browsers['is_konqueror'])) {
			$this->_browsers['is_konqueror'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'Konqueror');
		}

		return $this->_browsers['is_konqueror'];
	}

	/**
	 * Determine if the browser is Gecko or not
	 *
	 * @return bool Whether or not this is a Gecko-based browser
	 */
	public function isGecko(): bool
	{
		if (!isset($this->_browsers['is_gecko'])) {
			$this->_browsers['is_gecko'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'Gecko') && !$this->isWebkit() && !$this->isKonqueror();
		}

		return $this->_browsers['is_gecko'];
	}

	/**
	 * Determine if the browser is Opera Mini or not
	 *
	 * @return bool Whether or not this is Opera Mini
	 */
	public function isOperaMini(): bool
	{
		if (!isset($this->_browsers['is_opera_mini'])) {
			$this->_browsers['is_opera_mini'] = (isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']) || stripos($_SERVER['HTTP_USER_AGENT'], 'opera mini') !== false);
		}

		if ($this->_browsers['is_opera_mini']) {
			$this->_is_mobile = true;
		}

		return $this->_browsers['is_opera_mini'];
	}

	/**
	 * Determine if the browser is Opera Mobile or not
	 *
	 * @return bool Whether or not this is Opera Mobile
	 */
	public function isOperaMobi(): bool
	{
		if (!isset($this->_browsers['is_opera_mobi'])) {
			$this->_browsers['is_opera_mobi'] = stripos($_SERVER['HTTP_USER_AGENT'], 'opera mobi') !== false;
		}

		if ($this->_browsers['is_opera_mobi']) {
			$this->_is_mobile = true;
		}

		return $this->_browsers['is_opera_mini'];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Detect Safari / Chrome / iP[ao]d / iPhone / Android / Blackberry from webkit.
	 *  - set the browser version for Safari and Chrome
	 *  - set the mobile flag for mobile based useragents
	 */
	private function setupWebkit(): void
	{
		$this->_browsers += [
			'is_chrome' => str_contains($_SERVER['HTTP_USER_AGENT'], 'Chrome'),
			'is_iphone' => (str_contains($_SERVER['HTTP_USER_AGENT'], 'iPhone') || str_contains($_SERVER['HTTP_USER_AGENT'], 'iPod')) && !str_contains($_SERVER['HTTP_USER_AGENT'], 'iPad'),
			'is_blackberry' => str_contains(strtolower($_SERVER['HTTP_USER_AGENT']), 'blackberry') || str_contains($_SERVER['HTTP_USER_AGENT'], 'PlayBook'),
			'is_android' => str_contains($_SERVER['HTTP_USER_AGENT'], 'Android'),
			'is_nokia' => str_contains($_SERVER['HTTP_USER_AGENT'], 'SymbianOS'),
		];

		// blackberry, playbook, iphone, nokia, android and ipods set a mobile flag
		if ($this->_browsers['is_iphone'] || $this->_browsers['is_blackberry'] || $this->_browsers['is_android'] || $this->_browsers['is_nokia']) {
			$this->_is_mobile = true;
		}

		// @todo what to do with the blaPad? ... for now leave it detected as Safari ...
		$this->_browsers['is_safari'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'Safari') && !$this->_browsers['is_chrome'] && !$this->_browsers['is_iphone'];
		$this->_browsers['is_ipad'] = str_contains($_SERVER['HTTP_USER_AGENT'], 'iPad');

		// if Chrome, get the major version
		if ($this->_browsers['is_chrome']) {
			if (preg_match('~chrome[/]([0-9][0-9]?[.])~i', $_SERVER['HTTP_USER_AGENT'], $match) === 1) {
				$this->_browsers['is_chrome' . (int) $match[1]] = true;
			}
		}

		// or if Safari get its major version
		if ($this->_browsers['is_safari']) {
			if (preg_match('~version/?(.*)safari.*~i', $_SERVER['HTTP_USER_AGENT'], $match) === 1) {
				$this->_browsers['is_safari' . (int) trim($match[1])] = true;
			}
		}
	}

	/**
	 * Additional IE checks and settings.
	 *  - determines the version of the IE browser in use
	 *  - detects ie4 onward
	 *  - attempts to distinguish between IE and IE in compatibility view
	 *  - checks for old IE on macs as well, since we can
	 */
	private function setupIe(): void
	{
		$this->_browsers['is_ie_compat_view'] = false;

		// get the version of the browser from the msie tag
		if (preg_match('~MSIE\s?([0-9][0-9]?.[0-9])~i', $_SERVER['HTTP_USER_AGENT'], $msie_match) === 1) {
			$msie_match[1] = trim($msie_match[1]);
			$msie_match[1] = (($msie_match[1] - (int) $msie_match[1]) == 0) ? (int) $msie_match[1] : $msie_match[1];
			$this->_browsers['is_ie' . $msie_match[1]] = true;
		}

		// "modern" ie uses trident 4=ie8, 5=ie9, 6=ie10, 7=ie11 even in compatibility view
		if (preg_match('~Trident/([0-9.])~i', $_SERVER['HTTP_USER_AGENT'], $trident_match) === 1) {
			$this->_browsers['is_ie' . ((int) $trident_match[1] + 4)] = true;

			// If trident is set, see the (if any) msie tag in the user agent matches ... if not it's in some compatibility view
			if (isset($msie_match[1]) && ($msie_match[1] < $trident_match[1] + 4)) {
				$this->_browsers['is_ie_compat_view'] = true;
			}
		}

		// Detect true IE6 and IE7 and not IE in compat mode.
		$this->_browsers['is_ie7'] = !empty($this->_browsers['is_ie7']) && ($this->_browsers['is_ie_compat_view'] === false);
		$this->_browsers['is_ie6'] = !empty($this->_browsers['is_ie6']) && ($this->_browsers['is_ie_compat_view'] === false);

		// IE mobile 7 or 9, ... shucks why not
		if ((!empty($this->_browsers['is_ie7']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'IEMobile/7')) || (!empty($this->_browsers['is_ie9']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'IEMobile/9'))) {
			$this->_browsers['is_ie_mobi'] = true;
			$this->_is_mobile = true;
		}

		// And some throwbacks to a bygone era, deposited here like cholesterol in your arteries
		$this->_browsers += [
			'is_ie4' => !empty($this->_browsers['is_ie4']) && !$this->_browsers['is_web_tv'],
			'is_mac_ie' => str_contains($_SERVER['HTTP_USER_AGENT'], 'MSIE 5.') && str_contains($_SERVER['HTTP_USER_AGENT'], 'Mac'),
		];

		// Before IE8 we need to fix IE... lots!
		$this->_browsers['ie_standards_fix'] = (($this->_browsers['is_ie6'] === true) || ($this->_browsers['is_ie7'] === true)) ? true : false;

		// We may even need a size fix...
		$this->_browsers['needs_size_fix'] = (!empty($this->_browsers['is_ie5']) || !empty($this->_browsers['is_ie5.5']) || !empty($this->_browsers['is_ie4'])) && !$this->_browsers['is_mac_ie'];
	}

	/**
	 * Additional firefox checks.
	 * - Gets the version of the FF browser in use
	 * - Considers all FF variants as FF including IceWeasel, IceCat, Shiretoko and Minefiled
	 */
	private function setupFirefox(): void
	{
		if (preg_match('~(?:Firefox|Ice[wW]easel|IceCat|Shiretoko|Minefield)[\/ \(]([^ ;\)]+)~', $_SERVER['HTTP_USER_AGENT'], $match) === 1) {
			$this->_browsers['is_firefox' . (int) $match[1]] = true;
		}
	}

	/**
	 * More Opera checks if we are opera.
	 *  - checks for the version of Opera in use
	 *  - uses checks for 10 first and falls through to <9
	 */
	private function setupOpera(): void
	{
		// Opera 10+ uses the version tag at the end of the string
		if (preg_match('~\sVersion/([0-9]+)\.[0-9]+(?:\s*|$)~', $_SERVER['HTTP_USER_AGENT'], $match)) {
			$this->_browsers['is_opera' . (int) $match[1]] = true;
		}
		// Opera pre 10 is supposed to uses the Opera tag alone, as do some spoofers
		elseif (preg_match('~Opera[ /]([0-9]+)(?!\.[89])~', $_SERVER['HTTP_USER_AGENT'], $match)) {
			$this->_browsers['is_opera' . (int) $match[1]] = true;
		}

		// Needs size fix?
		$this->_browsers['needs_size_fix'] = !empty($this->_browsers['is_opera6']);
	}

	/**
	 * Sets the version number for MS edge.
	 */
	private function setupEdge(): void
	{
		if (preg_match('~Edge[\/]([0-9][0-9]?[\.][0-9][0-9])~i', $_SERVER['HTTP_USER_AGENT'], $match) === 1) {
			$this->_browsers['is_edge' . (int) $match[1]] = true;
		}
	}

	/**
	 * Get the browser name that we will use in the <body id="this_browser">
	 *  - The order of each browser in $browser_priority is important
	 *  - if you want to have id='ie6' and not id='ie' then it must appear first in the list of ie browsers
	 *  - only sets browsers that may need some help via css for compatibility
	 */
	private function setupBrowserPriority(): void
	{
		if ($this->_is_mobile) {
			Utils::$context['browser_body_id'] = 'mobile';
		} else {
			// add in any specific detection conversions here if you want a special body id e.g. 'is_opera9' => 'opera9'
			$browser_priority = [
				'is_ie6' => 'ie6',
				'is_ie7' => 'ie7',
				'is_ie8' => 'ie8',
				'is_ie9' => 'ie9',
				'is_ie10' => 'ie10',
				'is_ie11' => 'ie11',
				'is_ie' => 'ie',
				'is_edge' => 'edge',
				'is_firefox' => 'firefox',
				'is_chrome' => 'chrome',
				'is_safari' => 'safari',
				'is_opera10' => 'opera10',
				'is_opera11' => 'opera11',
				'is_opera12' => 'opera12',
				'is_opera' => 'opera',
				'is_konqueror' => 'konqueror',
			];

			Utils::$context['browser_body_id'] = 'smf';
			$active = array_reverse(array_keys($this->_browsers, true));

			foreach ($active as $browser) {
				if (array_key_exists($browser, $browser_priority)) {
					Utils::$context['browser_body_id'] = $browser_priority[$browser];
					break;
				}
			}
		}
	}

	/**
	 * Fill out the historical array
	 *  - needed to support old mods that don't use isBrowser
	 */
	private function fillInformation(): void
	{
		$this->_browsers += [
			'is_opera' => false,
			'is_opera6' => false,
			'is_opera7' => false,
			'is_opera8' => false,
			'is_opera9' => false,
			'is_opera10' => false,
			'is_webkit' => false,
			'is_mac_ie' => false,
			'is_web_tv' => false,
			'is_konqueror' => false,
			'is_firefox' => false,
			'is_firefox1' => false,
			'is_firefox2' => false,
			'is_firefox3' => false,
			'is_iphone' => false,
			'is_android' => false,
			'is_chrome' => false,
			'is_safari' => false,
			'is_gecko' => false,
			'is_edge' => false,
			'is_ie8' => false,
			'is_ie7' => false,
			'is_ie6' => false,
			'is_ie5.5' => false,
			'is_ie5' => false,
			'is_ie' => false,
			'is_ie4' => false,
			'ie_standards_fix' => false,
			'needs_size_fix' => false,
			'possibly_robot' => false,
		];
	}
}

?>