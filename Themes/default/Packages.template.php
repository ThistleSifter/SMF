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

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Url;
use SMF\Utils;
use SMF\Sapi;

/**
 * The main template
 */
function template_main()
{
}

/**
 * View package details when installing/uninstalling
 */
function template_view_package()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt[(Utils::$context['uninstalling'] ? 'uninstall' : ('install_' . Utils::$context['extract_type']))], '</h3>
		</div>
		<div class="information">';

	if (Utils::$context['is_installed'])
		echo '
			<strong>', Lang::$txt['package_installed_warning1'], '</strong><br>
			<br>
			', Lang::$txt['package_installed_warning2'], '<br>
			<br>';

	echo Lang::$txt['package_installed_warning3'], '
		</div>
		<br>';

	if (!empty(Utils::$context['package_blacklist_found']))
		echo '
		<div class="errorbox">', Lang::$txt['package_validation_blacklist_found'], '
		</div>';

	// Do errors exist in the install? If so light them up like a christmas tree.
	if (Utils::$context['has_failure'])
		echo '
		<div class="errorbox">
			', Lang::getTxt('package_will_fail_title', [Lang::$txt['package_' . (Utils::$context['uninstalling'] ? 'uninstall' : 'install')]]), '<br>
			', Lang::getTxt('package_will_fail_warning', [Lang::$txt['package_' . (Utils::$context['uninstalling'] ? 'uninstall' : 'install')]]),
			!empty(Utils::$context['failure_details']) ? '<br><br><strong>' . Utils::$context['failure_details'] . '</strong>' : '', '
		</div>';

	// Validation info?
	if (!empty(Utils::$context['validation_tests']))
	{
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', Lang::$txt['package_validaiton_results'], '</h3>
		</div>
		<div id="package_validation">
			<table class="table_grid">';

		foreach (Utils::$context['validation_tests'] as $id_server => $result)
		{
			echo '
			<tr>
				<td>', Utils::$context['package_servers'][$id_server]['name'], '</td>
				<td>', Lang::$txt[isset($result[Utils::$context['package_sha256_hash']]) ? $result[Utils::$context['package_sha256_hash']] : 'package_validation_status_unknown'], '</td>
			</tr>';
		}

		echo '
			</table>
		</div>
		<br>';
	}

	// Display the package readme if one exists
	if (isset(Utils::$context['package_readme']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['package_' . (Utils::$context['uninstalling'] ? 'un' : '') . 'install_readme'], '</h3>
		</div>
		<div class="windowbg">
			', Utils::$context['package_readme'], '
			<span class="floatright">', Lang::$txt['package_available_readme_language'], '
				<select name="readme_language" id="readme_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = smf_prepareScriptUrl(smf_scripturl + \'', '?action=admin;area=packages;sa=', Utils::$context['uninstalling'] ? 'uninstall' : 'install', ';package=', Utils::$context['filename'], ';license=\' + this.options[this.selectedIndex].value + \';readme=\' + get_selected(\'readme_language\'));">';

		foreach (Utils::$context['readmes'] as $a => $b)
			echo '
					<option value="', $b, '"', $a === 'selected' ? ' selected' : '', '>', $b == 'default' ? Lang::$txt['package_readme_default'] : ucfirst($b), '</option>';

		echo '
				</select>
			</span>
		</div>
		<br>';
	}

	// Did they specify a license to display?
	if (isset(Utils::$context['package_license']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['package_install_license'], '</h3>
		</div>
		<div class="windowbg">
			', Utils::$context['package_license'], '
			<span class="floatright">', Lang::$txt['package_available_license_language'], '
				<select name="license_language" id="license_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = smf_prepareScriptUrl(smf_scripturl + \'', '?action=admin;area=packages;sa=install', ';package=', Utils::$context['filename'], ';readme=\' + this.options[this.selectedIndex].value + \';license=\' + get_selected(\'license_language\'));">';

		foreach (Utils::$context['licenses'] as $a => $b)
			echo '
					<option value="', $b, '"', $a === 'selected' ? ' selected' : '', '>', $b == 'default' ? Lang::$txt['package_license_default'] : ucfirst($b), '</option>';
		echo '
				</select>
			</span>
		</div>
		<br>';
	}

	echo '
		<form action="', !empty(Utils::$context['post_url']) ? Utils::$context['post_url'] : '#', '" onsubmit="submitonce(this);" method="post" accept-charset="', Utils::$context['character_set'], '" id="view_package">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::getTxt(Utils::$context['uninstalling'] ? 'package_uninstall_actions' : 'package_install_actions', Utils::$context), '
				</h3>
			</div>';

	// Are there data changes to be removed?
	if (Utils::$context['uninstalling'] && !empty(Utils::$context['database_changes']))
	{
		// This is really a special case so we're adding style inline
		echo '
			<div class="windowbg" style="margin: 0; border-radius: 0;">
				<label><input type="checkbox" name="do_db_changes">', Lang::$txt['package_db_uninstall'], '</label>
				<div id="db_changes_div">
					', Lang::$txt['package_db_uninstall_actions'], '
					<ul class="normallist smalltext">';

		foreach (Utils::$context['database_changes'] as $change)
			echo '
						<li>', $change, '</li>';

		echo '
					</ul>
				</div>
			</div>';
	}

	echo '
			<div class="information">';

	if (empty(Utils::$context['actions']) && empty(Utils::$context['database_changes']))
		echo '
				<br>
				<div class="errorbox">
					', Lang::$txt['corrupt_compatible'], '
				</div>
			</div><!-- .information -->';
	else
	{
		echo '
				', Lang::$txt['perform_actions'], '
			</div><!-- .information -->
			<br>
			<table class="table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col"></th>
						<th scope="col" width="30"></th>
						<th scope="col" class="lefttext">', Lang::$txt['package_install_type'], '</th>
						<th scope="col" class="lefttext" width="50%">', Lang::$txt['package_install_action'], '</th>
						<th class="lefttext" scope="col" width="20%">', Lang::$txt['package_install_desc'], '</th>
					</tr>
				</thead>
				<tbody>';

		$i = 1;
		$j = 1;
		$action_num = 1;
		$js_operations = array();
		foreach (Utils::$context['actions'] as $packageaction)
		{
			// Did we pass or fail?  Need to now for later on.
			$js_operations[$action_num] = isset($packageaction['failed']) ? $packageaction['failed'] : 0;

			echo '
					<tr class="bg ', $i % 2 == 0 ? 'even' : 'odd', '">
						<td>', isset($packageaction['operations']) ? '<img id="operation_img_' . $action_num . '" src="' . Theme::$current->settings['images_url'] . '/selected_open.png" alt="*" style="display: none;">' : '', '</td>
						<td style="width: 30px;">', $i++, '.</td>
						<td style="width: 23%;">', $packageaction['type'], '</td>
						<td style="width: 50%;">', $packageaction['action'], '</td>
						<td style="width: 20%;"><strong', !empty($packageaction['failed']) ? ' class="error"' : '', '>', $packageaction['description'], '</strong></td>
					</tr>';

			// Is there water on the knee? Operation!
			if (isset($packageaction['operations']))
			{
				echo '
					<tr id="operation_', $action_num, '">
						<td colspan="5">
							<table class="full_width">';

				// Show the operations.
				$operation_num = 1;
				foreach ($packageaction['operations'] as $operation)
				{
					// Determine the position text.
					$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');

					echo '
								<tr class="bg ', $operation_num % 2 == 0 ? 'even' : 'odd', '">
									<td class="righttext">
										<a href="', Config::$scripturl, '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], !empty(Utils::$context['install_id']) ? ';install_id=' . Utils::$context['install_id'] : '', ';package=', $_REQUEST['package'], ';filename=', $operation['filename'], ($operation['is_boardmod'] ? ';boardmod' : ''), (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'uninstall' ? ';reverse' : ''), '" onclick="return reqWin(this.href, 600, 400, false);">
											<span class="main_icons package_ops"></span>
										</a>
									</td>
									<td width="30">', $operation_num++, '.</td>
									<td width="23%">', Lang::$txt[$operation_text], '</td>
									<td width="50%">', $operation['action'], '</td>
									<td width="20%"><strong', !empty($operation['failed']) ? ' class="error"' : '', '>', !empty($operation['ignore_failure']) ? Lang::getTxt('operation_description_ignore', ['desc' => $operation['description']]) : $operation['description'], '</strong></td>
								</tr>';
				}

				echo '
							</table>
						</td>
					</tr>';

				// Increase it.
				$action_num++;
			}
		}
		echo '
				</tbody>
			</table>';

		// What if we have custom themes we can install into? List them too!
		if (!empty(Utils::$context['theme_actions']))
		{
			echo '
			<br>
			<div class="cat_bar">
				<h3 class="catbg">
					', Utils::$context['uninstalling'] ? Lang::$txt['package_other_themes_uninstall'] : Lang::$txt['package_other_themes'], '
				</h3>
			</div>
			<div id="custom_changes">
				<div class="information">
					', Lang::$txt['package_other_themes_desc'], '
				</div>
				<table class="table_grid">';

			// Loop through each theme and display its name, and then it's details.
			foreach (Utils::$context['theme_actions'] as $id => $theme)
			{
				// Pass?
				$js_operations[$action_num] = !empty($theme['has_failure']);

				echo '
					<tr class="title_bar">
						<td class="righttext" colspan="2">';

				if (!empty(Utils::$context['themes_locked']))
					echo '
							<input type="hidden" name="custom_theme[]" value="', $id, '">';
				echo '
							<input type="checkbox" name="custom_theme[]" id="custom_theme_', $id, '" value="', $id, '" onclick="', (!empty($theme['has_failure']) ? 'if (this.form.custom_theme_' . $id . '.checked && !confirm(\'' . Lang::$txt['package_theme_failure_warning'] . '\')) return false;' : ''), 'invertAll(this, this.form, \'dummy_theme_', $id, '\', true);"', !empty(Utils::$context['themes_locked']) ? ' disabled checked' : '', '>
						</td>
						<td colspan="3">
							', $theme['name'], '
						</td>
					</tr>';

				foreach ($theme['actions'] as $action)
				{
					echo '
					<tr class="bg ', $j++ % 2 == 0 ? 'even' : 'odd', '">
						<td colspan="2">', isset($packageaction['operations']) ?
							'<img id="operation_img_' . $action_num . '" src="' . Theme::$current->settings['images_url'] . '/selected_open.png" alt="*" style="display: none;">' : '', '
							<input type="checkbox" name="theme_changes[]" value="', !empty($action['value']) ? $action['value'] : '', '" id="dummy_theme_', $id, '"', (!empty($action['not_mod']) ? '' : ' disabled'), !empty(Utils::$context['themes_locked']) ? ' checked' : '', ' class="floatright">
						</td>
						<td width="23%">', $action['type'], '</td>
						<td width="50%">', $action['action'], '</td>
						<td width="20%"><strong', !empty($action['failed']) ? ' class="error"' : '', '>', $action['description'], '</strong></td>
					</tr>';

					// Is there water on the knee? Operation!
					if (isset($action['operations']))
					{
						echo '
					<tr id="operation_', $action_num, '">
						<td colspan="5">
							<table class="full_width">';

						$operation_num = 1;
						foreach ($action['operations'] as $operation)
						{
							// Determine the position text.
							$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');

							echo '
								<tr class="bg ', $operation_num % 2 == 0 ? 'even' : 'odd', '">
									<td class="righttext">
										<a href="', Config::$scripturl, '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], !empty(Utils::$context['install_id']) ? ';install_id=' . Utils::$context['install_id'] : '', ';package=', $_REQUEST['package'], ';filename=', $operation['filename'], ($operation['is_boardmod'] ? ';boardmod' : ''), (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'uninstall' ? ';reverse' : ''), '" onclick="return reqWin(this.href, 600, 400, false);">
											<span class="main_icons package_ops"></span>
										</a>
									</td>
									<td width="30">', $operation_num++, '.</td>
									<td width="23%">', Lang::$txt[$operation_text], '</td>
									<td width="50%">', $operation['action'], '</td>
									<td width="20%"><strong', !empty($operation['failed']) ? ' class="error"' : '', '>', !empty($operation['ignore_failure']) ? Lang::getTxt('operation_description_ignore', ['desc' => $operation['description']]) : $operation['description'], '</strong></td>
								</tr>';
						}

						echo '
							</table>
						</td>
					</tr>';

						// Increase it.
						$action_num++;
					}
				}
			}

			echo '
				</table>
			</div><!-- #custom_changes -->';
		}
	}

	// Are we effectively ready to install?
	if (!Utils::$context['ftp_needed'] && (!empty(Utils::$context['actions']) || !empty(Utils::$context['database_changes'])))
		echo '
			<div class="righttext padding">
				<input type="submit" value="', Utils::$context['uninstalling'] ? Lang::$txt['package_uninstall_now'] : Lang::$txt['package_install_now'], '" onclick="return ', !empty(Utils::$context['has_failure']) ? '(submitThisOnce(this) &amp;&amp; confirm(\'' . (Utils::$context['uninstalling'] ? Lang::$txt['package_will_fail_popup_uninstall'] : Lang::$txt['package_will_fail_popup']) . '\'))' : 'submitThisOnce(this)', ';" class="button">
			</div>';

	// If we need ftp information then demand it!
	elseif (Utils::$context['ftp_needed'])
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['package_ftp_necessary'], '</h3>
			</div>
			<div>
				', template_control_chmod(), '
			</div>';

	echo '

			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">', (isset(Utils::$context['form_sequence_number']) && !Utils::$context['ftp_needed']) ? '
			<input type="hidden" name="seqnum" value="' . Utils::$context['form_sequence_number'] . '">' : '', '
		</form>';

	// Toggle options.
	echo '
	<script>';

	// Operations.
	if (!empty($js_operations))
		foreach ($js_operations as $key => $operation)
			echo '
		new smc_Toggle({
			bToggleEnabled: true,
			bNoAnimate: true,
			bCurrentlyCollapsed: ', $operation ? 'false' : 'true', ',
			aSwappableContainers: [
				\'operation_', $key, '\'
			],
			aSwapImages: [
				{
					sId: \'operation_img_', $key, '\',
					srcExpanded: smf_images_url + \'/selected_open.png\',
					altExpanded: \'*\',
					srcCollapsed: smf_images_url + \'/selected.png\',
					altCollapsed: \'*\'
				}
			]
		});';

	// Get the currently selected item from a select list
	echo '
		function get_selected(id)
		{
			var aSelected = document.getElementById(id);
			for (var i = 0; i < aSelected.options.length; i++)
			{
				if (aSelected.options[i].selected == true)
					return aSelected.options[i].value;
			}
			return aSelected.options[0];
		}';

	// And a bit more for database changes.
	if (Utils::$context['uninstalling'] && !empty(Utils::$context['database_changes']))
		echo '
		makeToggle(document.getElementById(\'db_changes_div\'), ', Utils::escapeJavaScript(Lang::$txt['package_db_uninstall_details']), ');';

	echo '
	</script>';
}

/**
 * Extract package contents
 */
function template_extract_package()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">';

	if (empty(Utils::$context['redirect_url']))
		echo Utils::$context['uninstalling'] ? Lang::$txt['uninstall'] : Lang::$txt['extracting'];
	else
		echo Lang::$txt['package_installed_redirecting'];

	echo '</h3>
		</div>
		<div class="windowbg">';

	// If we are going to redirect we have a slightly different agenda.
	if (!empty(Utils::$context['redirect_url']))
		echo '
			', Utils::$context['redirect_text'], '<br><br>
			<a href="', Utils::$context['redirect_url'], '">', Lang::$txt['package_installed_redirect_go_now'], '</a><span id="countdown" class="hidden"> (5) </span> | <a href="', Config::$scripturl, '?action=admin;area=packages;sa=browse">', Lang::$txt['package_installed_redirect_cancel'], '</a>
			<script>
				var countdown = ', Utils::$context['redirect_timeout'], ';
				var el = document.getElementById(\'countdown\');
				var loop = setInterval(doCountdown, 1000);

				function doCountdown()
				{
					countdown--;
					el.textContent = " (" + countdown + ") ";

					if (countdown == 0)
					{
						clearInterval(loop);
						window.location = "', Utils::$context['redirect_url'], '";
					}
				}
				el.classList.remove(\'hidden\');
				el.value = " (" + countdown + ") ";
			</script>';

	elseif (Utils::$context['uninstalling'])
		echo '
			', Lang::$txt['package_uninstall_done'] .' <br>
			', '<a href="', Utils::$context['keep_url'], '" class="button">', Lang::$txt['package_keep'], '</a>', '<a href="', Utils::$context['remove_url'], '" class="button">', Lang::$txt['package_delete2'], '</a>';

	elseif (Utils::$context['install_finished'])
	{
		if (Utils::$context['extract_type'] == 'avatar')
			echo '
			', Lang::$txt['avatars_extracted'];

		elseif (Utils::$context['extract_type'] == 'language')
			echo '
			', Lang::$txt['language_extracted'];

		else
			echo '
			', Lang::$txt['package_installed_done'];
	}
	else
		echo '
			', Lang::$txt['corrupt_compatible'];

	echo '
		</div><!-- .windowbg -->';

	// Show the "restore permissions" screen?
	if (function_exists('template_show_list') && !empty(Utils::$context['restore_file_permissions']['rows']))
	{
		echo '<br>';
		template_show_list('restore_file_permissions');
	}
}

/**
 * List files in a package
 */
function template_list()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['list_file'], '</h3>
		</div>
		<div class="title_bar">
			<h4 class="titlebg">', Lang::getTxt('files_package', Utils::$context), '</h4>
		</div>
		<div class="windowbg">
			<ol>';

	foreach (Utils::$context['files'] as $fileinfo)
		echo '
				<li><a href="', Config::$scripturl, '?action=admin;area=packages;sa=examine;package=', Utils::$context['filename'], ';file=', $fileinfo['filename'], '" title="', Lang::$txt['view'], '">', $fileinfo['filename'], '</a> ', Lang::getTxt('package_bytes', $fileinfo), '</li>';

	echo '
			</ol>
			<br>
			<a href="', Config::$scripturl, '?action=admin;area=packages" class="button floatnone">', Lang::$txt['back'], '</a>
		</div>';
}

/**
 * Examine a single file within a package
 */
function template_examine()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['package_examine_file'], '</h3>
		</div>
		<div class="title_bar">
			<h4 class="titlebg">', Lang::getTxt('package_file_contents', Utils::$context), '</h4>
		</div>
		<div class="windowbg">
			<pre class="file_content">', Utils::$context['filedata'], '</pre>
			<a href="', Config::$scripturl, '?action=admin;area=packages;sa=list;package=', Utils::$context['package'], '" class="button floatnone">', Lang::$txt['list_files'], '</a>
		</div>';
}

/**
 * List all packages
 */
function template_browse()
{
	echo '
		<div id="update_section"></div>
		<div id="admin_form_wrapper">
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::$txt['packages_adding_title'], '
				</h3>
			</div>
			<div class="information">
				', Lang::$txt['packages_adding'], '
			</div>

			<script>
				window.smfForum_scripturl = smf_scripturl;
				window.smfForum_sessionid = smf_session_id;
				window.smfForum_sessionvar = smf_session_var;';

	// Make a list of already installed mods so nothing is listed twice ;).
	echo '
				window.smfInstalledPackages = ["', implode('", "', Utils::$context['installed_mods']), '"];
				window.smfVersion = "', Utils::$context['forum_version'], '";
			</script>
			<div id="yourVersion" style="display:none">', Utils::$context['forum_version'], '</div>';

	if (empty(Config::$modSettings['disable_smf_js']))
		echo '
			<script src="', Config::$scripturl, '?action=viewsmfile;filename=latest-news.js"></script>';

	// This sets the announcements and current versions themselves ;).
	echo '
			<script>
				var oAdminIndex = new smf_AdminIndex({
					sSelf: \'oAdminCenter\',
					bLoadAnnouncements: false,
					bLoadVersions: false,
					bLoadUpdateNotification: true,
					sUpdateNotificationContainerId: \'update_section\',
					sUpdateNotificationDefaultTitle: ', Utils::escapeJavaScript(Lang::$txt['update_available']), ',
					sUpdateNotificationDefaultMessage: ', Utils::escapeJavaScript(Lang::$txt['update_message']), ',
					sUpdateNotificationTemplate: ', Utils::escapeJavaScript('
						<h3 id="update_title">
							%title%
						</h3>
						<div id="update_message" class="smalltext">
							%message%
						</div>
					'), ',
					sUpdateNotificationLink: smf_scripturl + ', Utils::escapeJavaScript('?action=admin;area=packages;pgdownload;auto;package=%package%;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '
				});
			</script>';

	echo '
		</div><!-- #admin_form_wrapper -->';

	if (Utils::$context['available_packages'] == 0)
		echo '
		<div class="noticebox">', Lang::$txt['no_packages'], '</div>';
	else
	{
		foreach (Utils::$context['modification_types'] as $type)
			if (!empty(Utils::$context['packages_lists_' . $type]['rows']))
				template_show_list('packages_lists_' . $type);

		echo '
		<br>';
	}

	// The advanced (emulation) box, collapsed by default
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=packages;sa=browse" method="get">
			<div id="advanced_box">
				<div class="cat_bar">
					<h3 class="catbg">
						<span id="advanced_panel_toggle" class="floatright" style="display: none;"></span>
						<a href="#" id="advanced_panel_link">', Lang::$txt['package_advanced_button'], '</a>
					</h3>
				</div>
				<div id="advanced_panel_div" class="windowbg">
					<p>
						', Lang::$txt['package_emulate_desc'], '
					</p>
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['package_emulate'], '</strong><br>
							<span class="smalltext">
								<a href="#" onclick="return revert();">', Lang::$txt['package_emulate_revert'], '</a>
							</span>
						</dt>
						<dd>
							<a id="revert" name="revert"></a>
							<select name="version_emulate" id="ve">';

	foreach (Utils::$context['emulation_versions'] as $version)
		echo '
								<option value="', $version, '"', ($version == Utils::$context['selected_version'] ? ' selected="selected"' : ''), '>', $version, '</option>';

	echo '
							</select>
						</dd>
					</dl>
					<div class="righttext padding">
						<input type="submit" value="', Lang::$txt['package_apply'], '" class="button">
					</div>
				</div><!-- #advanced_panel_div -->
			</div><!-- #advanced_box -->
			<input type="hidden" name="action" value="admin">
			<input type="hidden" name="area" value="packages">
			<input type="hidden" name="sa" value="browse">
		</form>
	<script>
		var oAdvancedPanelToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				\'advanced_panel_div\'
			],
			aSwapImages: [
				{
					sId: \'advanced_panel_toggle\',
					altExpanded: ', Utils::escapeJavaScript(Lang::$txt['hide']), ',
					altCollapsed: ', Utils::escapeJavaScript(Lang::$txt['show']), '
				}
			],
			aSwapLinks: [
				{
					sId: \'advanced_panel_link\',
					msgExpanded: ', Utils::escapeJavaScript(Lang::$txt['package_advanced_button']), ',
					msgCollapsed: ', Utils::escapeJavaScript(Lang::$txt['package_advanced_button']), '
				}
			]
		});
		function revert()
		{
			var default_version = "', Utils::$context['default_version'], '";
			$("#ve").find("option").filter(function(index) {
				return default_version === $(this).text();
			}).attr("selected", "selected");
			return false;
		}
	</script>';
}

/**
 * List package servers
 */
function template_servers()
{
	if (!empty(Utils::$context['package_ftp']['error']))
		echo '
	<div class="errorbox">
		<pre>', Utils::$context['package_ftp']['error'], '</pre>
	</div>';

	echo '
	<div id="admin_form_wrapper">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['package_upload_title'], '</h3>
		</div>
		<div class="windowbg">
			<form action="', Config::$scripturl, '?action=admin;area=packages;get;sa=upload" method="post" accept-charset="', Utils::$context['character_set'], '" enctype="multipart/form-data">
				<dl class="settings">
					<dt>
						<strong>', Lang::$txt['package_upload_select'], '</strong>
					</dt>
					<dd>
						<input type="file" name="package" size="38">
					</dd>
				</dl>
				<input type="submit" value="', Lang::$txt['upload'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</form>
		</div>
		<div class="cat_bar">
			<h3 class="catbg">
				<a class="download_new_package">
					<span class="toggle_down floatright" alt="*" title="', Lang::$txt['show'], '"></span>
					', Lang::$txt['download_new_package'], '
				</a>
			</h3>
		</div>
		<div class="new_package_content">';

	if (Utils::$context['package_download_broken'])
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['package_ftp_necessary'], '</h3>
			</div>
			<div class="windowbg">
				<p>
					', Lang::$txt['package_ftp_why_download'], '
				</p>
				<form action="', Config::$scripturl, '?action=admin;area=packages;get" method="post" accept-charset="', Utils::$context['character_set'], '">
					<dl class="settings">
						<dt>
							<label for="ftp_server">', Lang::$txt['package_ftp_server'], '</label>
						</dt>
						<dd>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="', Utils::$context['package_ftp']['server'], '">
							<label for="ftp_port">', Lang::$txt['package_ftp_port'], '</label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', Utils::$context['package_ftp']['port'], '">
						</dd>
						<dt>
							<label for="ftp_username">', Lang::$txt['package_ftp_username'], '</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', Utils::$context['package_ftp']['username'], '">
						</dd>
						<dt>
							<label for="ftp_password">', Lang::$txt['package_ftp_password'], '</label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password">
						</dd>
						<dt>
							<label for="ftp_path">', Lang::$txt['package_ftp_path'], '</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="', Utils::$context['package_ftp']['path'], '">
						</dd>
					</dl>
					<div class="righttext">
						<input type="submit" value="', Lang::$txt['package_proceed'], '" class="button">
					</div>
				</form>
			</div><!-- .windowbg -->';
	}

	echo '
			<div class="windowbg">
				<fieldset>
					<legend>' . Lang::$txt['package_servers'] . '</legend>
					<ul class="package_servers">';

	foreach (Utils::$context['servers'] as $server)
		echo '
						<li class="flow_auto">
							<span class="floatleft">' . $server['name'] . '</span>
							<span class="package_server floatright"><a href="' . Config::$scripturl . '?action=admin;area=packages;get;sa=browse;server=' . $server['id'] . '" class="button">' . Lang::$txt['package_browse'] . '</a></span>
							' . (!str_ends_with((new Url($server['url']))->host, '.simplemachines.org') ? '<span class="package_server floatright"><a href="' . Config::$scripturl . '?action=admin;area=packages;get;sa=remove;server=' . $server['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" class="button">' . Lang::$txt['delete'] . '</a></span>' : '') . '
						</li>';
	echo '
					</ul>
				</fieldset>
				<fieldset>
					<legend>' . Lang::$txt['add_server'] . '</legend>
					<form action="' . Config::$scripturl . '?action=admin;area=packages;get;sa=add" method="post" accept-charset="', Utils::$context['character_set'], '">
						<dl class="settings">
							<dt>
								<strong>' . Lang::$txt['server_name'] . '</strong>
							</dt>
							<dd>
								<input type="text" name="servername" size="44" value="SMF">
							</dd>
							<dt>
								<strong>' . Lang::$txt['serverurl'] . '</strong>
							</dt>
							<dd>
								<input type="text" name="serverurl" size="44" value="https://">
							</dd>
						</dl>
						<div class="righttext">
							<input type="submit" value="' . Lang::$txt['add_server'] . '" class="button">
							<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
						</div>
					</form>
				</fieldset>
				<fieldset>
					<legend>', Lang::$txt['package_download_by_url'], '</legend>
					<form action="', Config::$scripturl, '?action=admin;area=packages;get;sa=download;byurl;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
						<dl class="settings">
							<dt>
								<strong>' . Lang::$txt['serverurl'] . '</strong>
							</dt>
							<dd>
								<input type="text" name="package" size="44" value="https://">
							</dd>
							<dt>
								<strong>', Lang::$txt['package_download_filename'], '</strong>
							</dt>
							<dd>
								<input type="text" name="filename" size="44"><br>
								<span class="smalltext">', Lang::$txt['package_download_filename_info'], '</span>
							</dd>
						</dl>
						<input type="submit" value="', Lang::$txt['download'], '" class="button">
					</form>
				</fieldset>
			</div><!-- .windowbg -->
		</div><!-- .new_package_content -->
	</div><!-- #admin_form_wrapper -->';
}

/**
 * Confirm package operation
 */
function template_package_confirm()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>', Utils::$context['confirm_message'], '</p>
			<a href="', Utils::$context['proceed_href'], '" class="button floatnone">', Lang::$txt['package_confirm_proceed'], '</a> <a href="JavaScript:history.go(-1);" class="button floatnone">', Lang::$txt['package_confirm_go_back'], '</a>
		</div>';
}

/**
 * List packages.
 */
function template_package_list()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['page_title'], '</h3>
		</div>
		<div class="windowbg">';

	// No packages, as yet.
	if (empty(Utils::$context['package_list']))
		echo '
			<ul>
				<li>', Lang::$txt['no_packages'], '</li>
			</ul>';

	// List out the packages...
	else
	{
		echo '
			<ul id="package_list">';

		foreach (Utils::$context['package_list'] as $i => $packageSection)
		{
			echo '
				<li>
					<strong><span id="ps_img_', $i, '" class="toggle_up" alt="*" style="display: none;"></span> ', $packageSection['title'], '</strong>';

			if (!empty($packageSection['text']))
				echo '
					<div class="sub_bar">
						<h3 class="subbg">', $packageSection['text'], '</h3>
					</div>';

			echo '
					<', Utils::$context['list_type'], ' id="package_section_', $i, '" class="packages">';

			foreach ($packageSection['items'] as $id => $package)
			{
				echo '
						<li>';

				// Textual message. Could be empty just for a blank line...
				if ($package['is_text'])
					echo '
							', empty($package['name']) ? '&nbsp;' : $package['name'];

				// This is supposed to be a rule..
				elseif ($package['is_line'])
					echo '
							<hr>';

				// A remote link.
				elseif ($package['is_remote'])
					echo '
							<strong>', $package['link'], '</strong>';

				// A title?
				elseif ($package['is_heading'] || $package['is_title'])
					echo '
							<strong>', $package['name'], '</strong>';

				// Otherwise, it's a package.
				else
				{
					// 1. Some mod [ Download ].
					echo '
						<strong><span id="ps_img_', $i, '_pkg_', $id, '" class="toggle_up" alt="*" style="display: none;"></span> ', $package['can_install'] || !empty($package['can_emulate_install']) ? '<strong>' . $package['name'] . '</strong> <a href="' . $package['download']['href'] . '" class="button floatnone">' . Lang::$txt['download'] . '</a>' : $package['name'], '</strong>
						<ul id="package_section_', $i, '_pkg_', $id, '" class="package_section">';

					// Show the mod type?
					if ($package['type'] != '')
						echo '
							<li class="package_section">
								', Lang::getTxt('package_type', ['type' => Utils::ucwords(Utils::strtolower($package['type']))]), '
							</li>';

					// Show the version number?
					if ($package['version'] != '')
						echo '
							<li class="package_section">
								', Lang::getTxt('package_version', $package), '
							</li>';

					// How 'bout the author?
					if (!empty($package['author']) && $package['author']['name'] != '' && isset($package['author']['link']))
						echo '
							<li class="package_section">
								', Lang::getTxt('package_author', ['author' => $package['author']['link']]), '
							</li>';

					// The homepage...
					if ($package['author']['website']['link'] != '')
						echo '
							<li class="package_section">
								', Lang::getTxt('author_website', $package['author']['website']), '
							</li>';

					// Description: bleh bleh!
					// Location of file: http://someplace/.
					echo '
							<li class="package_section">
								', Lang::getTxt('file_location', ['link' => '<a href="' . $package['href'] . '">' . $package['href'] . '</a>']), '
							</li>
							<li class="package_section">
								', Lang::getTxt('package_description', $package), '
							</li>
						</ul>';
				}

				echo '
					</li>';
			}
			echo '
				</', Utils::$context['list_type'], '>
				</li>';
		}
		echo '
			</ul>';
	}

	echo '
		</div><!-- .windowbg -->';

	// Now go through and turn off all the sections.
	if (!empty(Utils::$context['package_list']))
	{
		$section_count = count(Utils::$context['package_list']);

		echo '
	<script>';

		foreach (Utils::$context['package_list'] as $section => $ps)
		{
			echo '
		var oPackageServerToggle_', $section, ' = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ', count($ps['items']) == 1 || $section_count == 1 ? 'false' : 'true', ',
			aSwappableContainers: [
				\'package_section_', $section, '\'
			],
			aSwapImages: [
				{
					sId: \'ps_img_', $section, '\',
					altExpanded: \'*\',
					altCollapsed: \'*\'
				}
			]
		});';

			foreach ($ps['items'] as $id => $package)
			{
				if (!$package['is_text'] && !$package['is_line'] && !$package['is_remote'])
					echo '
		var oPackageToggle_', $section, '_pkg_', $id, ' = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				\'package_section_', $section, '_pkg_', $id, '\'
			],
			aSwapImages: [
				{
					sId: \'ps_img_', $section, '_pkg_', $id, '\',
					altExpanded: \'*\',
					altCollapsed: \'*\'
				}
			]
		});';
			}
		}

		echo '
	</script>';
	}
}

/**
 * Confirmation page showing a package was uploaded/downloaded successfully.
 */
function template_downloaded()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<p>
				', (empty(Utils::$context['package_server']) ? Lang::$txt['package_uploaded_successfully'] : Lang::$txt['package_downloaded_successfully']), '
			</p>
			<ul>
				<li>
					<span class="floatleft"><strong>', Utils::$context['package']['name'], '</strong></span>
					<span class="package_server floatright">', Utils::$context['package']['list_files']['link'], '</span>
					<span class="package_server floatright">', Utils::$context['package']['install']['link'], '</span>
				</li>
			</ul>
			<br><br>
			<p><a href="', Config::$scripturl, '?action=admin;area=packages;get', (isset(Utils::$context['package_server']) ? ';sa=browse;server=' . Utils::$context['package_server'] : ''), '" class="button floatnone">', Lang::$txt['back'], '</a></p>
		</div>';
}

/**
 * Installation options - FTP info and backup settings
 */
function template_install_options()
{
	if (!empty(Utils::$context['saved_successful']))
		echo '
	<div class="infobox">', Lang::$txt['settings_saved'], '</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['package_install_options'], '</h3>
		</div>
		<div class="information noup">
			', Lang::$txt['package_install_options_ftp_why'], '
		</div>
		<div class="windowbg noup">
			<form action="', Config::$scripturl, '?action=admin;area=packages;sa=options" method="post" accept-charset="', Utils::$context['character_set'], '">
				<dl class="settings">
					<dt>
						<label for="pack_server"><strong>', Lang::$txt['package_install_options_ftp_server'], '</strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_server" id="pack_server" value="', Utils::$context['package_ftp_server'], '" size="30">
					</dd>
					<dt>
						<label for="pack_port"><strong>', Lang::$txt['package_install_options_ftp_port'], '</strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_port" id="pack_port" size="3" value="', Utils::$context['package_ftp_port'], '">
					</dd>
					<dt>
						<label for="pack_user"><strong>', Lang::$txt['package_install_options_ftp_user'], '</strong></label>
					</dt>
					<dd>
						<input type="text" name="pack_user" id="pack_user" value="', Utils::$context['package_ftp_username'], '" size="30">
					</dd>
					<dt>
						<label for="package_make_backups">', Lang::$txt['package_install_options_make_backups'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="package_make_backups" id="package_make_backups" value="1"', Utils::$context['package_make_backups'] ? ' checked' : '', '>
					</dd>
					<dt>
						<label for="package_make_full_backups">', Lang::$txt['package_install_options_make_full_backups'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="package_make_full_backups" id="package_make_full_backups" value="1"', Utils::$context['package_make_full_backups'] ? ' checked' : '', '>
					</dd>
				</dl>

				<input type="submit" name="save" value="', Lang::$txt['save'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</form>
		</div><!-- .windowbg -->';
}

/**
 * CHMOD control form
 *
 * @return bool False if nothing to do.
 */
function template_control_chmod()
{
	// Nothing to do? Brilliant!
	if (empty(Utils::$context['package_ftp']))
		return false;

	if (empty(Utils::$context['package_ftp']['form_elements_only']))
	{
		echo '
				', Lang::getTxt('package_ftp_why', ['onclick' => 'document.getElementById(\'need_writable_list\').style.display = \'\'; return false;']), '<br>
				<div id="need_writable_list" class="smalltext">
					', Lang::$txt['package_ftp_why_file_list'], '
					<ul style="display: inline;">';

		if (!empty(Utils::$context['notwritable_files']))
			foreach (Utils::$context['notwritable_files'] as $file)
				echo '
						<li>', $file, '</li>';

		echo '
					</ul>';

		if (!Sapi::isOS(Sapi::OS_WINDOWS))
			echo '
					<hr>
					', Lang::$txt['package_chmod_linux'], '<br>
					<samp># chmod a+w ', implode(' ', Utils::$context['notwritable_files']), '</samp>';

		echo '
				</div><!-- #need_writable_list -->';
	}

	echo '
				<div class="bordercolor" id="ftp_error_div" style="', (!empty(Utils::$context['package_ftp']['error']) ? '' : 'display:none;'), 'padding: 1px; margin: 1ex;">
					<div class="windowbg" id="ftp_error_innerdiv" style="padding: 1ex;">
						<samp id="ftp_error_message">', !empty(Utils::$context['package_ftp']['error']) ? Utils::$context['package_ftp']['error'] : '', '</samp>
					</div>
				</div>';

	if (!empty(Utils::$context['package_ftp']['destination']))
		echo '
				<form action="', Utils::$context['package_ftp']['destination'], '" method="post" accept-charset="', Utils::$context['character_set'], '">';

	echo '
					<fieldset>
					<dl class="settings">
						<dt>
							<label for="ftp_server">', Lang::$txt['package_ftp_server'], '</label>
						</dt>
						<dd>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="', Utils::$context['package_ftp']['server'], '">
							<label for="ftp_port">', Lang::$txt['package_ftp_port'], '</label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', Utils::$context['package_ftp']['port'], '">
						</dd>
						<dt>
							<label for="ftp_username">', Lang::$txt['package_ftp_username'], '</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', Utils::$context['package_ftp']['username'], '">
						</dd>
						<dt>
							<label for="ftp_password">', Lang::$txt['package_ftp_password'], '</label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password">
						</dd>
						<dt>
							<label for="ftp_path">', Lang::$txt['package_ftp_path'], '</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="', Utils::$context['package_ftp']['path'], '">
						</dd>
					</dl>
					</fieldset>';

	if (empty(Utils::$context['package_ftp']['form_elements_only']))
		echo '
					<div class="righttext" style="margin: 1ex;">
						<span id="test_ftp_placeholder_full"></span>
						<input type="submit" value="', Lang::$txt['package_proceed'], '" class="button">
					</div>';

	if (!empty(Utils::$context['package_ftp']['destination']))
		echo '
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				</form>';

	// Hide the details of the list.
	if (empty(Utils::$context['package_ftp']['form_elements_only']))
		echo '
				<script>
					document.getElementById(\'need_writable_list\').style.display = \'none\';
				</script>';

	// Quick generate the test button.
	echo '
				<script>
					// Generate a "test ftp" button.
					var generatedButton = false;
					function generateFTPTest()
					{
						// Don\'t ever call this twice!
						if (generatedButton)
							return false;
						generatedButton = true;

						// No XML?
						if (!window.XMLHttpRequest || (!document.getElementById("test_ftp_placeholder") && !document.getElementById("test_ftp_placeholder_full")))
							return false;

						var ftpTest = document.createElement("input");
						ftpTest.type = "button";
						ftpTest.onclick = testFTP;

						if (document.getElementById("test_ftp_placeholder"))
						{
							ftpTest.value = "', Lang::$txt['package_ftp_test'], '";
							document.getElementById("test_ftp_placeholder").appendChild(ftpTest);
						}
						else
						{
							ftpTest.value = "', Lang::$txt['package_ftp_test_connection'], '";
							document.getElementById("test_ftp_placeholder_full").appendChild(ftpTest);
						}
					}
					function testFTPResults(oXMLDoc)
					{
						ajax_indicator(false);

						// This assumes it went wrong!
						var wasSuccess = false;
						var message = "', addcslashes(Lang::$txt['package_ftp_test_failed'], "'"), '";

						var results = oXMLDoc.getElementsByTagName(\'results\')[0].getElementsByTagName(\'result\');
						if (results.length > 0)
						{
							if (results[0].getAttribute(\'success\') == 1)
								wasSuccess = true;
							message = results[0].firstChild.nodeValue;
						}

						document.getElementById("ftp_error_div").style.display = "";
						document.getElementById("ftp_error_div").style.backgroundColor = wasSuccess ? "green" : "red";
						document.getElementById("ftp_error_innerdiv").style.backgroundColor = wasSuccess ? "#DBFDC7" : "#FDBDBD";

						setInnerHTML(document.getElementById("ftp_error_message"), message);
					}
					generateFTPTest();
				</script>';

	// Make sure the button gets generated last.
	Utils::$context['insert_after_template'] .= '
				<script>
					generateFTPTest();
				</script>';
}

/**
 * Wrapper for the above template function showing that FTP is required
 */
function template_ftp_required()
{
	echo '
		<fieldset>
			<legend>
				', Lang::$txt['package_ftp_necessary'], '
			</legend>
			<div class="ftp_details">
				', template_control_chmod(), '
			</div>
		</fieldset>';
}

/**
 * View operation details.
 */
function template_view_operations()
{
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Utils::$context['character_set'], '">
		<title>', Lang::$txt['operation_title'], '</title>
		', Theme::template_css();

	Theme::template_javascript();

	echo '
	</head>
	<body>
		<div class="padding windowbg">
			<div class="padding">
				', Utils::$context['operations']['search'], '
			</div>
			<div class="padding">
				', Utils::$context['operations']['replace'], '
			</div>
		</div>
	</body>
</html>';
}

/**
 * The file permissions page.
 */
function template_file_permissions()
{
	// This will handle expanding the selection.
	echo '
	<script>
		var oRadioValues = {
			0: "read",
			1: "writable",
			2: "execute",
			3: "custom",
			4: "no_change"
		}
		function dynamicAddMore()
		{
			ajax_indicator(true);

			getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=admin;area=packages;fileoffset=\' + (parseInt(this.offset) + ', Utils::$context['file_limit'], ') + \';onlyfind=\' + escape(this.path) + \';sa=perms;xml;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '\', onNewFolderReceived);
		}

		// Getting something back?
		function onNewFolderReceived(oXMLDoc)
		{
			ajax_indicator(false);

			var fileItems = oXMLDoc.getElementsByTagName(\'folders\')[0].getElementsByTagName(\'folder\');

			// No folders, no longer worth going further.
			if (fileItems.length < 1)
			{
				if (oXMLDoc.getElementsByTagName(\'roots\')[0].getElementsByTagName(\'root\')[0])
				{
					var rootName = oXMLDoc.getElementsByTagName(\'roots\')[0].getElementsByTagName(\'root\')[0].firstChild.nodeValue;
					var itemLink = document.getElementById(\'link_\' + rootName);

					// Move the children up.
					for (i = 0; i <= itemLink.childNodes.length; i++)
						itemLink.parentNode.insertBefore(itemLink.childNodes[0], itemLink);

					// And remove the link.
					itemLink.parentNode.removeChild(itemLink);
				}
				return false;
			}
			var tableHandle = false;
			var isMore = false;
			var ident = "";
			var my_ident = "";
			var curLevel = 0;

			for (var i = 0; i < fileItems.length; i++)
			{
				if (fileItems[i].getAttribute(\'more\') == 1)
				{
					isMore = true;
					var curOffset = fileItems[i].getAttribute(\'offset\');
				}

				if (fileItems[i].getAttribute(\'more\') != 1 && document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\')))
				{
					ident = fileItems[i].getAttribute(\'ident\');
					my_ident = fileItems[i].getAttribute(\'my_ident\');
					curLevel = fileItems[i].getAttribute(\'level\') * 5;
					curPath = fileItems[i].getAttribute(\'path\');

					// Get where we\'re putting it next to.
					tableHandle = document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\'));

					var curRow = document.createElement("tr");
					curRow.className = "windowbg";
					curRow.id = "content_" + my_ident;
					curRow.style.display = "";
					var curCol = document.createElement("td");
					curCol.className = "smalltext";
					curCol.width = "40%";

					// This is the name.
					var fileName = document.createTextNode(fileItems[i].firstChild.nodeValue);

					// Start by wacking in the spaces.
					setInnerHTML(curCol, repeatString("&nbsp;", curLevel));

					// Create the actual text.
					if (fileItems[i].getAttribute(\'folder\') == 1)
					{
						var linkData = document.createElement("a");
						linkData.name = "fol_" + my_ident;
						linkData.id = "link_" + my_ident;
						linkData.href = \'#\';
						linkData.path = curPath + "/" + fileItems[i].firstChild.nodeValue;
						linkData.ident = my_ident;
						linkData.onclick = dynamicExpandFolder;

						var folderImage = document.createElement("span");
						folderImage.className = "main_icons folder";
						linkData.appendChild(folderImage);

						linkData.appendChild(fileName);
						curCol.appendChild(linkData);
					}
					else
						curCol.appendChild(fileName);

					curRow.appendChild(curCol);

					// Right, the permissions.
					curCol = document.createElement("td");
					curCol.className = "smalltext";

					var writeSpan = document.createElement("span");
					writeSpan.className = fileItems[i].getAttribute(\'writable\') ? "green" : "red";
					setInnerHTML(writeSpan, fileItems[i].getAttribute(\'writable\') ? \'', Lang::$txt['package_file_perms_writable'], '\' : \'', Lang::$txt['package_file_perms_not_writable'], '\');
					curCol.appendChild(writeSpan);

					if (fileItems[i].getAttribute(\'permissions\'))
					{
						var permData = document.createTextNode("\u00a0(', Lang::$txt['package_file_perms_chmod'], ': " + fileItems[i].getAttribute(\'permissions\') + ")");
						curCol.appendChild(permData);
					}

					curRow.appendChild(curCol);

					// Now add the five radio buttons.
					for (j = 0; j < 5; j++)
					{
						curCol = document.createElement("td");
						curCol.className = "centertext perm_" + oRadioValues[j];
						curCol.align = "center";

						var curInput = createNamedElement("input", "permStatus[" + curPath + "/" + fileItems[i].firstChild.nodeValue + "]", j == 4 ? "checked" : "");
						curInput.type = "radio";
						curInput.checked = "checked";
						curInput.value = oRadioValues[j];

						curCol.appendChild(curInput);
						curRow.appendChild(curCol);
					}

					// Put the row in.
					tableHandle.parentNode.insertBefore(curRow, tableHandle);

					// Put in a new dummy section?
					if (fileItems[i].getAttribute(\'folder\') == 1)
					{
						var newRow = document.createElement("tr");
						newRow.id = "insert_div_loc_" + my_ident;
						newRow.style.display = "none";
						tableHandle.parentNode.insertBefore(newRow, tableHandle);
						var newCol = document.createElement("td");
						newCol.colspan = 2;
						newRow.appendChild(newCol);
					}
				}
			}

			// Is there some more to remove?
			if (document.getElementById("content_" + ident + "_more"))
			{
				document.getElementById("content_" + ident + "_more").parentNode.removeChild(document.getElementById("content_" + ident + "_more"));
			}

			// Add more?
			if (isMore && tableHandle)
			{
				// Create the actual link.
				var linkData = document.createElement("a");
				linkData.href = \'#fol_\' + my_ident;
				linkData.path = curPath;
				linkData.offset = curOffset;
				linkData.onclick = dynamicAddMore;

				linkData.appendChild(document.createTextNode(\'', Lang::$txt['package_file_perms_more_files'], '\'));

				curRow = document.createElement("tr");
				curRow.className = "windowbg";
				curRow.id = "content_" + ident + "_more";
				tableHandle.parentNode.insertBefore(curRow, tableHandle);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curCol.width = "40%";

				setInnerHTML(curCol, repeatString("&nbsp;", curLevel));
				curCol.appendChild(document.createTextNode(\'\\u00ab \'));
				curCol.appendChild(linkData);
				curCol.appendChild(document.createTextNode(\' \\u00bb\'));

				curRow.appendChild(curCol);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curRow.appendChild(curCol);
			}

			// Keep track of it.
			var curInput = createNamedElement("input", "back_look[]");
			curInput.type = "hidden";
			curInput.value = curPath;

			curCol.appendChild(curInput);
		}
	</script>';

	echo '
	<div class="noticebox">
		<div>
			<strong>', Lang::$txt['package_file_perms_warning'], '</strong>
			<div class="smalltext">
				<ol style="margin-top: 2px; margin-bottom: 2px">
					', Lang::$txt['package_file_perms_warning_desc'], '
				</ol>
			</div>
		</div>
	</div>

	<form action="', Config::$scripturl, '?action=admin;area=packages;sa=perms;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatleft">', Lang::$txt['package_file_perms'], '</span><span class="perms_status floatright">', Lang::$txt['package_file_perms_new_status'], '</span>
			</h3>
		</div>
		<table class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext" width="30%">', Lang::$txt['package_file_perms_name'], '</th>
					<th width="30%" class="lefttext">', Lang::$txt['package_file_perms_status'], '</th>
					<th width="8%"><span class="file_permissions">', Lang::$txt['package_file_perms_status_read'], '</span></th>
					<th width="8%"><span class="file_permissions">', Lang::$txt['package_file_perms_status_write'], '</span></th>
					<th width="8%"><span class="file_permissions">', Lang::$txt['package_file_perms_status_execute'], '</span></th>
					<th width="8%"><span class="file_permissions">', Lang::$txt['package_file_perms_status_custom'], '</span></th>
					<th width="8%"><span class="file_permissions">', Lang::$txt['package_file_perms_status_no_change'], '</span></th>
				</tr>
			</thead>
			<tbody>';

	foreach (Utils::$context['file_tree'] as $name => $dir)
	{
		echo '
				<tr class="windowbg">
					<td width="30%">
						<strong>';

		if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
			echo '
							<span class="main_icons folder"></span>';

		echo '
							', $name, '
						</strong>
					</td>
					<td width="30%">
						<span style="color: ', ($dir['perms']['chmod'] ? 'green' : 'red'), '">', ($dir['perms']['chmod'] ? Lang::$txt['package_file_perms_writable'] : Lang::$txt['package_file_perms_not_writable']), '</span>
						', ($dir['perms']['perms'] ? ' (' . Lang::$txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
					</td>
					<td class="centertext perm_read">
						<input type="radio" name="permStatus[', $name, ']" value="read" class="centertext">
					</td>
					<td class="centertext perm_writable">
						<input type="radio" name="permStatus[', $name, ']" value="writable" class="centertext">
					</td>
					<td class="centertext perm_execute">
						<input type="radio" name="permStatus[', $name, ']" value="execute" class="centertext">
					</td>
					<td class="centertext perm_custom">
						<input type="radio" name="permStatus[', $name, ']" value="custom" class="centertext">
					</td>
					<td class="centertext perm_no_change">
						<input type="radio" name="permStatus[', $name, ']" value="no_change" checked class="centertext">
					</td>
				</tr>';

		if (!empty($dir['contents']))
			template_permission_show_contents($name, $dir['contents'], 1);
	}

	echo '
			</tbody>
		</table>
		<br>
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['package_file_perms_change'], '</h3>
		</div>
		<div class="windowbg">
			<fieldset>
				<dl>
					<dt>
						<input type="radio" name="method" value="individual" checked id="method_individual">
						<label for="method_individual"><strong>', Lang::$txt['package_file_perms_apply'], '</strong></label>
					</dt>
					<dd>
						<em class="smalltext">', Lang::$txt['package_file_perms_custom'], ' <input type="text" name="custom_value" value="0755" maxlength="4" size="5"> <a href="', Config::$scripturl, '?action=helpadmin;help=chmod_flags" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a></em>
					</dd>
					<dt>
						<input type="radio" name="method" value="predefined" id="method_predefined">
						<label for="method_predefined"><strong>', Lang::$txt['package_file_perms_predefined'], '</strong></label>
						<select name="predefined" onchange="document.getElementById(\'method_predefined\').checked = \'checked\';">
							<option value="restricted" selected>', Lang::$txt['package_file_perms_pre_restricted'], '</option>
							<option value="standard">', Lang::$txt['package_file_perms_pre_standard'], '</option>
							<option value="free">', Lang::$txt['package_file_perms_pre_free'], '</option>
						</select>
					</dt>
					<dd>
						<em class="smalltext">', Lang::$txt['package_file_perms_predefined_note'], '</em>
					</dd>
				</dl>
			</fieldset>';

	// Likely to need FTP?
	if (empty(Utils::$context['ftp_connected']))
		echo '
			<p>
				', Lang::$txt['package_file_perms_ftp_details'], '
			</p>
			', template_control_chmod(), '
			<div class="noticebox">', Lang::$txt['package_file_perms_ftp_retain'], '</div>';

	echo '
			<span id="test_ftp_placeholder_full"></span>
			<input type="hidden" name="action_changes" value="1">
			<input type="submit" value="', Lang::$txt['package_file_perms_go'], '" name="go" class="button">
		</div><!-- .windowbg -->';

	// Any looks fors we've already done?
	foreach (Utils::$context['look_for'] as $path)
		echo '
		<input type="hidden" name="back_look[]" value="', $path, '">';

	echo '
	</form>
	<br>';
}

/**
 * Shows permissions for items within a directory (called from template_file_permissions)
 *
 * @param string $ident A unique ID - typically the directory name
 * @param array $contents An array of items within the directory
 * @param int $level How far to go inside the directory
 * @param bool $has_more Whether there are more files to display besides what's in $contents
 */
function template_permission_show_contents($ident, $contents, $level, $has_more = false)
{
	$js_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident);

	// Have we actually done something?
	$drawn_div = false;

	foreach ($contents as $name => $dir)
	{
		if (isset($dir['perms']))
		{
			if (!$drawn_div)
			{
				$drawn_div = true;
				echo '
			</tbody>
			<tbody class="table_grid" id="', $js_ident, '">';
			}

			$cur_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident . '/' . $name);

			echo '
				<tr class="windowbg" id="content_', $cur_ident, '">
					<td class="smalltext" width="30%">' . str_repeat('&nbsp;', $level * 5), '
					', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '<a id="link_' . $cur_ident . '" href="' . Config::$scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident . '/' . $name) . ';back_look=' . Utils::$context['back_look_data'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '#fol_' . $cur_ident . '" onclick="return expandFolder(\'' . $cur_ident . '\', \'' . addcslashes($ident . '/' . $name, "'\\") . '\');">' : '';

			if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
				echo '
						<span class="main_icons folder"></span>';

			echo '
						', $name, '
						', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '</a>' : '', '
					</td>
					<td class="smalltext">
						<span class="', ($dir['perms']['chmod'] ? 'success' : 'error'), '">', ($dir['perms']['chmod'] ? Lang::$txt['package_file_perms_writable'] : Lang::$txt['package_file_perms_not_writable']), '</span>
						', ($dir['perms']['perms'] ? ' (' . Lang::$txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
					</td>
					<td class="centertext perm_read"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="read"></td>
					<td class="centertext perm_writable"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="writable"></td>
					<td class="centertext perm_execute"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="execute"></td>
					<td class="centertext perm_custom"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="custom"></td>
					<td class="centertext perm_no_change"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="no_change" checked></td>
				</tr>
				<tr id="insert_div_loc_' . $cur_ident . '" style="display: none;"><td></td></tr>';

			if (!empty($dir['contents']))
				template_permission_show_contents($ident . '/' . $name, $dir['contents'], $level + 1, !empty($dir['more_files']));
		}
	}

	// We have more files to show?
	if ($has_more)
		echo '
				<tr class="windowbg" id="content_', $js_ident, '_more">
					<td class="smalltext" width="40%">' . str_repeat('&nbsp;', $level * 5), '
						<a href="' . Config::$scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident) . ';fileoffset=', (Utils::$context['file_offset'] + Utils::$context['file_limit']), ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '#fol_' . preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident) . '">', Lang::$txt['package_file_perms_more_files'], '</a>
					</td>
					<td colspan="6"></td>
				</tr>';

	if ($drawn_div)
	{
		// Hide anything too far down the tree.
		$isFound = false;
		foreach (Utils::$context['look_for'] as $tree)
			if (str_starts_with($tree, $ident))
				$isFound = true;

		if ($level > 1 && !$isFound)
			echo '
		<script>
			expandFolder(\'', $js_ident, '\', \'\');
		</script>';
	}
}

/**
 * A progress page showing what permissions changes are being applied
 */
function template_action_permissions()
{
	$countDown = 3;

	echo '
		<form action="', Config::$scripturl, '?action=admin;area=packages;sa=perms;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" id="perm_submit" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['package_file_perms_applying'], '</h3>
			</div>';

	if (!empty(Utils::$context['skip_ftp']))
		echo '
			<div class="errorbox">
				', Lang::$txt['package_file_perms_skipping_ftp'], '
			</div>';

	// How many have we done?
	$remaining_items = count(Utils::$context['method'] == 'individual' ? Utils::$context['to_process'] : Utils::$context['directory_list']);

	$progress_message = Lang::getTxt(
		Utils::$context['method'] == 'individual' ? 'package_file_perms_items_done' : 'package_file_perms_dirs_done',
		[
			Utils::$context['total_items'] - $remaining_items,
			Utils::$context['total_items']
		]
	);

	$progress_percent = round((Utils::$context['total_items'] - $remaining_items) / Utils::$context['total_items'] * 100, 1);

	echo '
			<div class="windowbg">
				<div>
					<strong>', $progress_message, '</strong><br>
					<div class="progress_bar progress_blue">
						<span>', $progress_percent, '%</span>
						<div class="bar" style="width: ', $progress_percent, '%;"></div>
					</div>
				</div>';

	// Second progress bar for a specific directory?
	if (Utils::$context['method'] != 'individual' && !empty(Utils::$context['total_files']))
	{
		$file_progress_message = Lang::getTxt(
			'package_file_perms_files_done',
			[
				Utils::$context['file_offset'],
				Utils::$context['total_files']
			]
		);

		$file_progress_percent = round(Utils::$context['file_offset'] / Utils::$context['total_files'] * 100, 1);

		echo '
				<br>
				<div>
					<strong>', $file_progress_message, '</strong><br>
					<div class="progress_bar">
						<span>', $file_progress_percent, '%</span>
						<div class="bar" style="width: ', $file_progress_percent, '%;"></div>
					</div>
				</div>';
	}

	echo '
				<br>';

	// Put out the right hidden data.
	if (Utils::$context['method'] == 'individual')
		echo '
				<input type="hidden" name="custom_value" value="', Utils::$context['custom_value'], '">
				<input type="hidden" name="totalItems" value="', Utils::$context['total_items'], '">
				<input type="hidden" name="toProcess" value="', Utils::$context['to_process_encode'], '">';
	else
		echo '
				<input type="hidden" name="predefined" value="', Utils::$context['predefined_type'], '">
				<input type="hidden" name="fileOffset" value="', Utils::$context['file_offset'], '">
				<input type="hidden" name="totalItems" value="', Utils::$context['total_items'], '">
				<input type="hidden" name="dirList" value="', Utils::$context['directory_list_encode'], '">
				<input type="hidden" name="specialFiles" value="', Utils::$context['special_files_encode'], '">';

	// Are we not using FTP for whatever reason.
	if (!empty(Utils::$context['skip_ftp']))
		echo '
				<input type="hidden" name="skip_ftp" value="1">';

	// Retain state.
	foreach (Utils::$context['back_look_data'] as $path)
		echo '
				<input type="hidden" name="back_look[]" value="', $path, '">';

	echo '
				<input type="hidden" name="method" value="', Utils::$context['method'], '">
				<input type="hidden" name="action_changes" value="1">
				<div class="righttext padding">
					<input type="submit" name="go" id="cont" value="', Lang::$txt['not_done_continue'], '" class="button">
				</div>
			</div><!-- .windowbg -->
		</form>';

	// Just the countdown stuff
	echo '
	<script>
		var countdown = ', $countDown, ';
		doAutoSubmit();

		function doAutoSubmit()
		{
			if (countdown == 0)
				document.forms.perm_submit.submit();
			else if (countdown == -1)
				return;

			document.getElementById(\'cont\').value = "', Lang::$txt['not_done_continue'], ' (" + countdown + ")";
			countdown--;

			setTimeout("doAutoSubmit();", 1000);
		}
	</script>';
}

?>