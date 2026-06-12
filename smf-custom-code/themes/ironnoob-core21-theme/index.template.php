<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.3
 */

/*	This template is, perhaps, the most important template in the theme. It
	contains the main template layer that displays the header and footer of
	the forum, namely with main_above and main_below. It also contains the
	menu sub template, which appropriately displays the menu; the init sub
	template, which is there to set the theme up; (init can be missing.) and
	the linktree sub template, which sorts out the link tree.

	The init sub template should load any data and set any hardcoded options.

	The main_above sub template is what is shown above the main content, and
	should contain anything that should be shown up there.

	The main_below sub template, conversely, is shown after the main content.
	It should probably contain the copyright statement and some other things.

	The linktree sub template should display the link tree, using the data
	in the $context['linktree'] variable.

	The menu sub template should display all the relevant buttons the user
	wants and or needs.

	For more information on the templating system, please see the site at:
	https://www.simplemachines.org/
*/

/**
 * Initialize the template... mainly little settings.
 */
function template_init()
{
	global $settings, $txt;

	/* $context, $options and $txt may be available for use, but may not be fully populated yet. */

	// The version this template/theme is for. This should probably be the version of SMF it was created for.
	$settings['theme_version'] = '2.1';

	// Set the following variable to true if this theme requires the optional theme strings file to be loaded.
	$settings['require_theme_strings'] = false;

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last and the first post on the message index and recent pages.
	$settings['avatars_on_indexes'] = false;

	// Set the following variable to true if this theme wants to display the avatar of the user that posted the last post on the board index.
	$settings['avatars_on_boardIndex'] = false;

	// Set the following variable to true if this theme wants to display the login and register buttons in the main forum menu.
	$settings['login_main_menu'] = false;

	// This defines the formatting for the page indexes used throughout the forum.
	$settings['page_index'] = array(
		'extra_before' => '<span class="pages">' . $txt['pages'] . '</span>',
		'previous_page' => '<span class="main_icons previous_page"></span>',
		'current_page' => '<span class="current_page">%1$d</span> ',
		'page' => '<a class="nav_page" href="{URL}">%2$s</a> ',
		'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
		'next_page' => '<span class="main_icons next_page"></span>',
		'extra_after' => '',
	);

	// Allow css/js files to be disabled for this specific theme.
	// Add the identifier as an array key. IE array('smf_script'); Some external files might not add identifiers, on those cases SMF uses its filename as reference.
	if (!isset($settings['disable_files']))
		$settings['disable_files'] = array();
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	global $context, $scripturl, $txt, $modSettings;

	// Show right to left, the language code, and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', !empty($txt['lang_locale']) ? ' lang="' . str_replace("_", "-", substr($txt['lang_locale'], 0, strcspn($txt['lang_locale'], "."))) . '"' : '', '>
<head>
	<meta charset="', $context['character_set'], '">';

	/*
		You don't need to manually load index.css, this will be set up for you.
		Note that RTL will also be loaded for you.
		To load other CSS and JS files you should use the functions
		loadCSSFile() and loadJavaScriptFile() respectively.
		This approach will let you take advantage of SMF's automatic CSS
		minimization and other benefits. You can, of course, manually add any
		other files you want after template_css() has been run.

	*	Short example:
			- CSS: loadCSSFile('filename.css', array('minimize' => true));
			- JS:  loadJavaScriptFile('filename.js', array('minimize' => true));
			You can also read more detailed usages of the parameters for these
			functions on the SMF wiki.

	*	Themes:
			The most efficient way of writing multi themes is to use a master
			index.css plus variant.css files. If you've set them up properly
			(through $settings['theme_variants']), the variant files will be loaded
			for you automatically.
			Additionally, tweaking the CSS for the editor requires you to include
			a custom 'jquery.sceditor.theme.css' file in the css folder if you need it.

	*	MODs:
			If you want to load CSS or JS files in here, the best way is to use the
			'integrate_load_theme' hook for adding multiple files, or using
			'integrate_pre_css_output', 'integrate_pre_javascript_output' for a single file.
	*/

	// load in any css from mods or themes so they can overwrite if wanted
	template_css();

	// load in any javascript files from mods and themes
	template_javascript();

	echo '
	<title>', $context['page_title_html_safe'], '</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">';

	// Content related meta tags, like description, keywords, Open Graph stuff, etc...
	foreach ($context['meta_tags'] as $meta_tag)
	{
		echo '
	<meta';

		foreach ($meta_tag as $meta_key => $meta_value)
			echo ' ', $meta_key, '="', $meta_value, '"';

		echo '>';
	}

	/*	What is your Lollipop's color?
		Theme Authors, you can change the color here to make sure your theme's main color gets visible on tab */
	echo '
	<meta name="theme-color" content="#557EA0">';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex">';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '">';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', $scripturl, '?action=help">
	<link rel="contents" href="', $scripturl, '">', ($context['allow_search'] ? '
	<link rel="search" href="' . $scripturl . '?action=search">' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?action=.xml;type=rss2', !empty($context['current_board']) ? ';board=' . $context['current_board'] : '', '">
	<link rel="alternate" type="application/atom+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['atom'], '" href="', $scripturl, '?action=.xml;type=atom', !empty($context['current_board']) ? ';board=' . $context['current_board'] : '', '">';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['links']['next']))
		echo '
	<link rel="next" href="', $context['links']['next'], '">';

	if (!empty($context['links']['prev']))
		echo '
	<link rel="prev" href="', $context['links']['prev'], '">';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0">';

	// Output any remaining HTML headers. (from mods, maybe?)
	echo $context['html_headers'];

	echo '
</head>
<body id="', $context['browser_body_id'], '" class="action_', !empty($context['current_action']) ? $context['current_action'] : (!empty($context['current_board']) ?
		'messageindex' : (!empty($context['current_topic']) ? 'display' : 'home')), !empty($context['current_board']) ? ' board_' . $context['current_board'] : '', '">
<div id="footerfix">';
}

/**
 * The upper part of the main template layer. This is the stuff that shows above the main forum content.
 */
function template_body_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings, $maintenance, $boardurl;

	$hello_member = isset($txt['hello_member']) ? $txt['hello_member'] : 'Hello,';
	$hello_guest = isset($txt['hello_guest']) ? $txt['hello_guest'] : 'Welcome,';
	$upshrink_description = isset($txt['upshrink_description']) ? $txt['upshrink_description'] : 'Shrink or expand the header.';

	echo '
<div id="mainframe"', !empty($settings['forum_width']) ? ' style="width: ' . $settings['forum_width'] . '"' : '', '>
	<div class="tborder">
		<div class="catbg clearfix">
			<img class="floatright" id="smflogo" src="', $settings['images_url'], '/smflogo.gif" alt="Simple Machines Forum">
			<h1 id="forum_name"><a id="top" href="', $scripturl, '">';

	if (!empty($context['header_logo_url_html_safe']))
		echo '<img src="', $context['header_logo_url_html_safe'], '" alt="', $context['forum_name_html_safe'], '">';
	elseif (!empty($boardurl))
		echo '<img src="', $boardurl, '/Logos/Original.png" alt="', $context['forum_name_html_safe'], '">';
	else
		echo $context['forum_name_html_safe'];

	echo '</a></h1>
		</div>
		<ul id="greeting_section" class="reset titlebg2 clearfix">
			<li id="time" class="smalltext floatright">
				', $context['current_time'], '
				<img id="upshrink" src="', $settings['images_url'], '/upshrink.gif" alt="*" title="', $upshrink_description, '" style="display: none;">
			</li>';

	if ($context['user']['is_logged'])
		echo '
			<li id="name">', $hello_member, ' <em><a href="', $scripturl, '?action=profile;u=', $context['user']['id'], '">', $context['user']['name'], '</a></em></li>';
	else
		echo '
			<li id="name">', $hello_guest, ' <em>', isset($txt['guest']) ? $txt['guest'] : 'Guest', '</em></li>';

	echo '
		</ul>';

	if ($context['user']['is_logged'] || empty($maintenance))
		echo '
		<div id="user_section" class="bordercolor"', empty($options['collapse_header']) ? '' : ' style="display: none;"', '>
			<div class="windowbg2 clearfix">';

	$has_header_avatar = !empty($context['user']['avatar']['image']) && (empty($context['user']['avatar']['url']) || strpos($context['user']['avatar']['url'], '/default.png') === false);

	if ($has_header_avatar)
		echo '
				<div id="myavatar"><a href="', $scripturl, '?action=profile;u=', $context['user']['id'], '">', $context['user']['avatar']['image'], '</a></div>';

	if ($context['user']['is_logged'])
	{
		echo '
				<ul class="reset"', $has_header_avatar ? ' style="overflow: hidden;"' : '', '>
					<li><a href="', $scripturl, '?action=profile;u=', $context['user']['id'], '">', isset($txt['profile']) ? $txt['profile'] : 'Profile', '</a></li>
					<li><a href="', $scripturl, '?action=profile;area=account;u=', $context['user']['id'], '">', isset($txt['account']) ? $txt['account'] : 'Account Settings', '</a></li>
					<li><a href="', $scripturl, '?action=profile;area=forumprofile;u=', $context['user']['id'], '">', isset($txt['forumprofile']) ? $txt['forumprofile'] : 'Forum Profile', '</a></li>
					', !empty($context['allow_pm']) ? '<li><a href="' . $scripturl . '?action=pm">' . (isset($txt['pm_short']) ? $txt['pm_short'] : 'Messages') . (!empty($context['user']['unread_messages']) ? ' (' . $context['user']['unread_messages'] . ')' : '') . '</a></li>' : '', '
					<li><a href="', $scripturl, '?action=profile;area=showalerts;u=', $context['user']['id'], '">', isset($txt['alerts']) ? $txt['alerts'] : 'Alerts', !empty($context['user']['alerts']) ? ' (' . $context['user']['alerts'] . ')' : '', '</a></li>
					<li><a href="', $scripturl, '?action=unread">', isset($txt['unread_since_visit']) ? $txt['unread_since_visit'] : $txt['view_unread_category'], '</a></li>
					<li><a href="', $scripturl, '?action=unreadreplies">', isset($txt['show_unread_replies']) ? $txt['show_unread_replies'] : $txt['unread_replies'], '</a></li>
					<li><a href="', $scripturl, '?action=logout;', $context['session_var'], '=', $context['session_id'], '">', isset($txt['logout']) ? $txt['logout'] : 'Logout', '</a></li>';

		if (!empty($context['in_maintenance']) && $context['user']['is_admin'])
			echo '
					<li class="notice">', $txt['maintain_mode_on'], '</li>';

		if (!empty($context['unapproved_members']))
			echo '
					<li><a href="', $scripturl, '?action=admin;area=viewmembers;sa=browse;type=approve">', $context['unapproved_members'], ' ', $txt['approve_members'], '</a> ', $txt['approve_members_waiting'], '</li>';

		if (!empty($context['open_mod_reports']) && !empty($context['show_open_reports']))
			echo '
					<li><a href="', $scripturl, '?action=moderate;area=reports">', sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']), '</a></li>';

		echo '
				</ul>';
	}
	elseif (empty($maintenance))
	{
		$login_url = !empty($context['login_url']) ? $context['login_url'] : $scripturl . '?action=login2';
		echo '
				<form class="windowbg" id="guest_form" action="', $login_url, '" method="post" accept-charset="', $context['character_set'], '">
					Please <a href="', $scripturl, '?action=login">log in</a> or <a href="', $scripturl, '?action=signup">sign up</a>.<br>
					<input type="text" name="user" size="10" class="input_text" aria-label="', isset($txt['username']) ? $txt['username'] : 'Username', '">
					<input type="password" name="passwrd" size="10" class="input_password" aria-label="', isset($txt['password']) ? $txt['password'] : 'Password', '">
					<select name="cookielength">
						<option value="60">', $txt['one_hour'], '</option>
						<option value="1440">', $txt['one_day'], '</option>
						<option value="10080">', $txt['one_week'], '</option>
						<option value="43200">', $txt['one_month'], '</option>
						<option value="-1" selected>', $txt['forever'], '</option>
					</select>
					<input type="submit" value="', $txt['login'], '" class="button_submit"><br>
					<span class="smalltext">Login with username, password and session length</span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

		if (!empty($context['login_token_var']) && !empty($context['login_token']))
			echo '
					<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '">';

		echo '
				</form>';
	}

	if ($context['user']['is_logged'] || empty($maintenance))
		echo '
			</div>
		</div>';

	echo '
		<div id="news_section" class="titlebg2 clearfix"', empty($options['collapse_header']) ? '' : ' style="display: none;"', '>';

	if (!empty($context['allow_search']))
	{
		echo '
			<form class="floatright" id="search_form" action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
				<a href="', $scripturl, '?action=search;advanced" title="', $txt['search_advanced'], '"><img id="advsearch" src="', $settings['images_url'], '/filter.gif" alt="', $txt['search_advanced'], '"></a>
				<input type="search" name="search" value="" style="width: 190px;" class="input_text">&nbsp;
				<input type="submit" name="search2" value="', $txt['search'], '" style="width: 11ex;" class="button_submit">
				<input type="hidden" name="advanced" value="0">';

		if (!empty($context['current_topic']))
			echo '
				<input type="hidden" name="sd_topic" value="', $context['current_topic'], '">';
		elseif (!empty($context['current_board']))
			echo '
				<input type="hidden" name="sd_brd" value="', $context['current_board'], '">';

		echo '
			</form>';
	}

	if (!empty($settings['enable_news']) && !empty($context['random_news_line']))
		echo '
			<div id="random_news"><h3>', $txt['news'], ':</h3><p>', $context['random_news_line'], '</p></div>';

	echo '
		</div>
	</div>';

	template_menu();
	theme_linktree();

	echo '
	<div id="bodyarea">';
}

/**
 * The stuff shown immediately below the main content, including the footer
 */
function template_body_below()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	</div>';

	echo '
	<div id="footerarea" class="headerpadding topmargin clearfix">
		<ul class="reset smalltext">
			', !empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']) ? '<li><a id="button_rss" href="' . $scripturl . '?action=.xml;type=rss2" class="new_win"><span>' . $txt['rss'] . '</span></a></li>' : '', '
			<li class="last"><a href="#top"><span>', isset($txt['go_up']) ? $txt['go_up'] : 'Go up', '</span></a></li>
		</ul>';

	if ($context['show_load_time'])
		echo '
		<p class="smalltext" id="show_loadtime">', sprintf($txt['page_created_full'], $context['load_time'], $context['load_queries']), '</p>';

	echo '
	</div>
</div>';
}

/**
 * This shows any deferred JavaScript and closes out the HTML
 */
function template_html_below()
{
	// Load in any javascipt that could be deferred to the end of the page
	template_javascript(true);

	echo '
</body>
</html>';
}

/**
 * Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
 *
 * @param bool $force_show Whether to force showing it even if settings say otherwise
 */
function theme_linktree($force_show = false)
{
	global $context, $shown_linktree;

	if (empty($context['linktree']) || (!empty($context['dont_default_linktree']) && !$force_show))
		return;

	echo '
	<ul class="linktree" id="linktree_', empty($shown_linktree) ? 'upper' : 'lower', '">';

	foreach ($context['linktree'] as $link_num => $tree)
	{
		echo '
		<li', ($link_num == count($context['linktree']) - 1) ? ' class="last"' : '', '>';

		if (isset($tree['extra_before']))
			echo $tree['extra_before'];

		if (isset($tree['url']))
			echo '<a href="', $tree['url'], '"><span>', $tree['name'], '</span></a>';
		else
			echo '<span>', $tree['name'], '</span>';

		if (isset($tree['extra_after']))
			echo $tree['extra_after'];

		if ($link_num != count($context['linktree']) - 1)
			echo ' &gt;';

		echo '
		</li>';
	}
	echo '
	</ul>';

	$shown_linktree = true;
}

/**
 * Show the menu up top. Something like [home] [help] [profile] [logout]...
 */
function template_menu()
{
	global $context;

	echo '
	<div class="main_menu">
		<ul class="reset clearfix">';

	$last_key = null;
	foreach ($context['menu_buttons'] as $act => $button)
		$last_key = $act;

	foreach ($context['menu_buttons'] as $act => $button)
	{
		$classes = array();
		if (!empty($button['active_button']))
			$classes[] = 'active';
		if ($act === $last_key)
			$classes[] = 'last';

		echo '
			<li id="button_', $act, '"', !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '', '>
				<a title="', !empty($button['alttitle']) ? $button['alttitle'] : $button['title'], '" href="', $button['href'], '"', isset($button['target']) ? ' target="' . $button['target'] . '"' : '', isset($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '', '>
					<span>', (!empty($button['active_button']) ? '<em>' : ''), $button['title'], (!empty($button['active_button']) ? '</em>' : ''), '</span>
				</a>';

		if (!empty($button['sub_buttons']))
		{
			echo '
				<ul>';
			foreach ($button['sub_buttons'] as $childbutton)
				echo '
					<li><a href="', $childbutton['href'], '"', isset($childbutton['target']) ? ' target="' . $childbutton['target'] . '"' : '', isset($childbutton['onclick']) ? ' onclick="' . $childbutton['onclick'] . '"' : '', '><span>', $childbutton['title'], '</span></a></li>';
			echo '
				</ul>';
		}

		echo '
			</li>';
	}

	echo '
		</ul>
	</div>';
}

/**
 * Generate a strip of buttons.
 *
 * @param array $button_strip An array with info for displaying the strip
 * @param string $direction The direction
 * @param array $strip_options Options for the button strip
 */
function template_button_strip($button_strip, $direction = '', $strip_options = array())
{
	global $context, $txt;

	if (!is_array($strip_options))
		$strip_options = array();

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		// As of 2.1, the 'test' for each button happens while the array is being generated. The extra 'test' check here is deprecated but kept for backward compatibility (update your mods, folks!)
		if (!isset($value['test']) || !empty($context[$value['test']]))
		{
			if (!isset($value['id']))
				$value['id'] = $key;

			$button = '
				<a class="button button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . (isset($value['class']) ? ' ' . $value['class'] : '') . '" ' . (!empty($value['url']) ? 'href="' . $value['url'] . '"' : '') . ' ' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>'.(!empty($value['icon']) ? '<span class="main_icons '.$value['icon'].'"></span>' : '').'' . $txt[$value['text']] . '</a>';

			if (!empty($value['sub_buttons']))
			{
				$button .= '
					<div class="top_menu dropmenu ' . $key . '_dropdown">
						<div class="viewport">
							<div class="overview">';
				foreach ($value['sub_buttons'] as $element)
				{
					if (isset($element['test']) && empty($context[$element['test']]))
						continue;

					$button .= '
								<a href="' . $element['url'] . '"><strong>' . $txt[$element['text']] . '</strong>';
					if (isset($txt[$element['text'] . '_desc']))
						$button .= '<br><span>' . $txt[$element['text'] . '_desc'] . '</span>';
					$button .= '</a>';
				}
				$button .= '
							</div><!-- .overview -->
						</div><!-- .viewport -->
					</div><!-- .top_menu -->';
			}

			$buttons[] = $button;
		}
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"' : ''), '>
			', implode('', $buttons), '
		</div>';
}

/**
 * Generate a list of quickbuttons.
 *
 * @param array $list_items An array with info for displaying the strip
 * @param string $list_class Used for integration hooks and as a class name
 * @param string $output_method The output method. If 'echo', simply displays the buttons, otherwise returns the HTML for them
 * @return void|string Returns nothing unless output_method is something other than 'echo'
 */
function template_quickbuttons($list_items, $list_class = null, $output_method = 'echo')
{
	global $txt;

	// Enable manipulation with hooks
	if (!empty($list_class))
		call_integration_hook('integrate_' . $list_class . '_quickbuttons', array(&$list_items));

	// Make sure the list has at least one shown item
	foreach ($list_items as $key => $li)
	{
		// Is there a sublist, and does it have any shown items
		if ($key == 'more')
		{
			foreach ($li as $subkey => $subli)
				if (isset($subli['show']) && !$subli['show'])
					unset($list_items[$key][$subkey]);

			if (empty($list_items[$key]))
				unset($list_items[$key]);
		}
		// A normal list item
		elseif (isset($li['show']) && !$li['show'])
			unset($list_items[$key]);
	}

	// Now check if there are any items left
	if (empty($list_items))
		return;

	// Print the quickbuttons
	$output = '
		<ul class="quickbuttons' . (!empty($list_class) ? ' quickbuttons_' . $list_class : '') . '">';

	// This is used for a list item or a sublist item
	$list_item_format = function($li)
	{
		$html = '
			<li' . (!empty($li['class']) ? ' class="' . $li['class'] . '"' : '') . (!empty($li['id']) ? ' id="' . $li['id'] . '"' : '') . (!empty($li['custom']) ? ' ' . $li['custom'] : '') . '>';

		if (isset($li['content']))
			$html .= $li['content'];
		else
			$html .= '
				<a href="' . (!empty($li['href']) ? $li['href'] : 'javascript:void(0);') . '"' . (!empty($li['javascript']) ? ' ' . $li['javascript'] : '') . '>
					' . (!empty($li['icon']) ? '<span class="main_icons ' . $li['icon'] . '"></span>' : '') . (!empty($li['label']) ? $li['label'] : '') . '
				</a>';

		$html .= '
			</li>';

		return $html;
	};

	foreach ($list_items as $key => $li)
	{
		// Handle the sublist
		if ($key == 'more')
		{
			$output .= '
			<li class="post_options">
				<a href="javascript:void(0);">' . $txt['post_options'] . '</a>
				<ul>';

			foreach ($li as $subli)
				$output .= $list_item_format($subli);

			$output .= '
				</ul>
			</li>';
		}
		// Ordinary list item
		else
			$output .= $list_item_format($li);
	}

	$output .= '
		</ul><!-- .quickbuttons -->';

	// There are a few spots where the result needs to be returned
	if ($output_method == 'echo')
		echo $output;
	else
		return $output;
}

/**
 * The upper part of the maintenance warning box
 */
function template_maint_warning_above()
{
	global $txt, $context, $scripturl;

	echo '
	<div class="errorbox" id="errors">
		<dl>
			<dt>
				<strong id="error_serious">', $txt['forum_in_maintenance'], '</strong>
			</dt>
			<dd class="error" id="error_list">
				', sprintf($txt['maintenance_page'], $scripturl . '?action=admin;area=serversettings;' . $context['session_var'] . '=' . $context['session_id']), '
			</dd>
		</dl>
	</div>';
}

/**
 * The lower part of the maintenance warning box.
 */
function template_maint_warning_below()
{

}

?>