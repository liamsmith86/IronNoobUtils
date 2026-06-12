<?php

if (!function_exists('core21_legacy_board_icon'))
{
	function core21_legacy_board_icon($board)
	{
		global $context, $settings, $scripturl, $txt;

		$is_redirect = !empty($board['is_redirect']) || (!empty($board['type']) && $board['type'] === 'redirect');
		$has_children_new = !empty($board['children_new']);
		$href = $is_redirect || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children';

		if (!empty($board['new']) || $has_children_new)
			$image = 'on' . (!empty($board['new']) ? '' : '2') . '.gif';
		elseif ($is_redirect)
			$image = 'redirect.gif';
		else
			$image = 'off.gif';

		$title = $is_redirect ? '*' : (!empty($board['new']) || $has_children_new ? $txt['new_posts'] : $txt['old_posts']);
		echo '<a href="', $href, '"><img src="', $settings['images_url'], '/', $image, '" alt="', $title, '" title="', $title, '"></a>';
	}
}

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.2
 */


if (!function_exists('core21_legacy_topic_class'))
{
	function core21_legacy_topic_class($topic)
	{
		$prefix = !empty($topic['is_posted_in']) ? 'my_' : '';
		$heat = 'normal';
		if (!empty($topic['is_very_hot']))
			$heat = 'veryhot';
		elseif (!empty($topic['is_hot']))
			$heat = 'hot';
		$type = !empty($topic['is_poll']) ? 'poll' : 'post';
		$suffix = '';
		if (!empty($topic['is_locked']))
			$suffix .= '_locked';
		if (!empty($topic['is_sticky']))
			$suffix .= '_sticky';
		return $prefix . $heat . '_' . $type . $suffix;
	}
}

/**
 * The main messageindex.
 */
function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '<a id="top"></a>';

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
	<div class="tborder marginbottom" id="childboards">
		<table cellspacing="1" class="bordercolor boardsframe">
			<tr>
				<td colspan="4" class="catbg headerpadding">', $txt['sub_boards'], '</td>
			</tr>';
		foreach ($context['boards'] as $board)
		{
			$is_redirect = !empty($board['is_redirect']) || (!empty($board['type']) && $board['type'] === 'redirect');
			echo '
			<tr>
				<td', !empty($board['children']) ? ' rowspan="2"' : '', ' class="windowbg icon">';
			core21_legacy_board_icon($board);
			echo '</td>
				<td class="windowbg2 info"><h4><a href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a></h4><p>', $board['description'], '</p></td>
				<td', !empty($board['children']) ? ' rowspan="2"' : '', ' class="windowbg stats smalltext">', comma_format($board['posts']), ' ', $is_redirect ? $txt['redirects'] : $txt['posts'], '<br>', $is_redirect ? '' : comma_format($board['topics']) . ' ' . $txt['board_topics'], '</td>
				<td', !empty($board['children']) ? ' rowspan="2"' : '', ' class="windowbg2 smalltext lastpost">', !empty($board['last_post']['last_post_message']) ? $board['last_post']['last_post_message'] : '', '</td>
			</tr>';
		}
		echo '
		</table>
	</div>';
	}

	if (!empty($context['description']))
		echo '
	<div id="description" class="tborder">
		<div class="titlebg2 largepadding smalltext">', $context['description'], '</div>
	</div>';

	if (!empty($context['becomesUnapproved']))
		echo '<div class="noticebox">', $txt['post_becomes_unapproved'], '</div>';
	if (!empty($context['unapproved_posts_message']))
		echo '<div class="noticebox">', $context['unapproved_posts_message'], '</div>';

	if (!$context['no_topic_listing'])
	{
		echo '
	<div id="modbuttons_top" class="modbuttons clearfix margintop">
		<div class="floatleft middletext">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '&nbsp;&nbsp;<a href="#bot"><strong>' . $txt['go_down'] . '</strong></a>' : '', '</div>
		', template_button_strip($context['normal_buttons'], 'bottom'), '
	</div>';

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm">';

		echo '
	<div class="tborder" id="messageindex">
		<table cellspacing="1" class="bordercolor boardsframe">';

		if (!empty($context['topics']))
		{
			echo '
			<thead>
				<tr>
					<th width="9%" colspan="2" class="catbg3 headerpadding">&nbsp;</th>
					<th class="catbg3 headerpadding">', $context['topics_headers']['subject'], '</th>
					<th class="catbg3 headerpadding" width="11%">', $context['topics_headers']['starter'], '</th>
					<th class="catbg3 headerpadding" width="4%" align="center">', $context['topics_headers']['replies'], '</th>
					<th class="catbg3 headerpadding" width="4%" align="center">', $context['topics_headers']['views'], '</th>
					<th class="catbg3 headerpadding" width="22%">', $context['topics_headers']['last_post'], '</th>';

			if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
				echo '
					<th class="catbg3 headerpadding" width="24"><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check"></th>';
			elseif (!empty($context['can_quick_mod']))
				echo '
					<th class="catbg3 headerpadding" width="4%">&nbsp;</th>';

			echo '
				</tr>
			</thead>';
		}

		echo '
			<tbody>';

		if (!empty($settings['display_who_viewing']))
			echo '
				<tr class="windowbg2"><td colspan="', !empty($context['can_quick_mod']) ? '8' : '7', '" class="headerpadding smalltext">', count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'], $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'], '</td></tr>';

		if (empty($context['topics']))
			echo '
				<tr class="windowbg2"><td class="catbg3" colspan="', !empty($context['can_quick_mod']) ? '8' : '7', '"><strong>', $txt['msg_alert_none'], '</strong></td></tr>';

		foreach ($context['topics'] as $topic)
		{
			if (!empty($context['can_approve_posts']) && !empty($topic['unapproved_posts']))
				$color_class = empty($topic['approved']) ? 'approvetbg' : 'approvebg';
			elseif (!empty($topic['is_sticky']))
				$color_class = 'windowbg3';
			else
				$color_class = 'windowbg';
			$alternate_class = 'windowbg2';
			$topic_class = core21_legacy_topic_class($topic);

			echo '
				<tr>
					<td class="', $alternate_class, ' icon1"><img src="', $settings['images_url'], '/topic/', $topic_class, '.gif" alt=""></td>
					<td class="', $alternate_class, ' icon2"><img src="', $topic['first_post']['icon_url'], '" alt=""></td>
					<td class="subject ', $color_class, '" ', !empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '" ondblclick="oQuickModifyTopic.modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : '', '>';

			if (!empty($topic['is_locked']))
				echo '<span class="main_icons lock floatright" id="lockicon', $topic['first_post']['id'], '"></span>';
			if (!empty($topic['is_sticky']))
				echo '<span class="main_icons sticky floatright" id="stickyicon', $topic['first_post']['id'], '"></span>';

			echo !empty($topic['is_sticky']) ? '<strong>' : '', '<span id="msg_', $topic['first_post']['id'], '">', $topic['first_post']['link'], empty($topic['approved']) ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : '', '</span>', !empty($topic['is_sticky']) ? '</strong>' : '';

			if (!empty($topic['new']) && $context['user']['is_logged'])
				echo ' <a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><img src="', $settings['lang_images_url'], '/new.gif" alt="', $txt['new'], '"></a>';

			echo '<small id="pages', $topic['first_post']['id'], '">', !empty($topic['pages']) ? $topic['pages'] : '', '</small>
					</td>
					<td class="', $alternate_class, ' starter">', $topic['first_post']['member']['link'], '</td>
					<td class="', $color_class, ' replies">', $topic['replies'], '</td>
					<td class="', $color_class, ' views">', $topic['views'], '</td>
					<td class="', $alternate_class, ' lastpost"><a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '"></a> <span class="smalltext">', $topic['last_post']['time'], '<br>', $txt['by'], ' ', $topic['last_post']['member']['link'], '</span></td>';

			if (!empty($context['can_quick_mod']))
			{
				echo '<td class="', $color_class, ' moderation">';
				if ($options['display_quick_mod'] == 1)
					echo '<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check">';
				echo '</td>';
			}

			echo '
				</tr>';
		}

		echo '
			</tbody>
		</table>
	</div>
	<a id="bot"></a>';

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';

		echo '
	<div id="modbuttons_bottom" class="modbuttons clearfix marginbottom">
		', template_button_strip($context['normal_buttons'], 'top'), '
		<div class="floatleft middletext">', $txt['pages'], ': ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '&nbsp;&nbsp;<a href="#top"><strong>' . $txt['go_up'] . '</strong></a>' : '', '</div>
	</div>';
	}

	echo '<div class="marginbottom">';
	theme_linktree();
	echo '</div>';

	template_topic_legend();

	echo '
	<script>
		var oQuickModifyTopic = new QuickModifyTopic({
			aHidePrefixes: Array("lockicon", "stickyicon", "pages", "newicon"),
			bMouseOnDiv: false,
		});
	</script>';
}

/**
 * Outputs the board icon for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_icon($board)
{
	global $context, $scripturl;

	echo '
		<a href="', ($context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '" class="board_', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>';
}

/**
 * Outputs the board icon for a redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_redirect_icon($board)
{
	global $context, $scripturl;

	echo '
		<a href="', $board['href'], '" class="board_', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>';
}

/**
 * Outputs the board info for a standard board or redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_board_info($board)
{
	global $context, $scripturl, $txt;

	echo '
		<a class="subject mobile_subject" href="', $board['href'], '" id="b', $board['id'], '">
			', $board['name'], '
		</a>';

	// Has it outstanding posts for approval?
	if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
		echo '
		<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link amt">!</a>';

	echo '
		<div class="board_description">', $board['description'], '</div>';

	// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
	if (!empty($board['moderators']) || !empty($board['moderator_groups']))
		echo '
		<p class="moderators">', count($board['link_moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';
}

/**
 * Outputs the board stats for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_stats($board)
{
	global $txt;

	echo '
		<p>
			', $txt['posts'], ': ', comma_format($board['posts']), '<br>', $txt['board_topics'], ': ', comma_format($board['topics']), '
		</p>';
}

/**
 * Outputs the board stats for a redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_redirect_stats($board)
{
	global $txt;

	echo '
		<p>
			', $txt['redirects'], ': ', comma_format($board['posts']), '
		</p>';
}

/**
 * Outputs the board lastposts for a standard board or a redirect.
 * When on a mobile device, this may be hidden if no last post exists.
 *
 * @param array $board Current board information.
 */
function template_bi_board_lastpost($board)
{
	if (!empty($board['last_post']['id']))
		echo '
			<p>', $board['last_post']['last_post_message'], '</p>';
}

/**
 * Outputs the board children for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_children($board)
{
	global $txt, $scripturl, $context;

	// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
	if (!empty($board['children']))
	{
		// Sort the links into an array with new boards bold so it can be imploded.
		$children = array();
		/* Each child in each board's children has:
			id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
		foreach ($board['children'] as $child)
		{
			if (!$child['is_redirect'])
				$child['link'] = '' . ($child['new'] ? '<a href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')" class="new_posts">' . $txt['new'] . '</a> ' : '') . '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . '</a>';
			else
				$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . ' - ' . $child['short_description'] . '">' . $child['name'] . '</a>';

			// Has it posts awaiting approval?
			if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
				$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link amt">!</a>';

			$children[] = $child['new'] ? '<span class="strong">' . $child['link'] . '</span>' : '<span>' . $child['link'] . '</span>';
		}

		echo '
			<div id="board_', $board['id'], '_children" class="children">
				<p><strong id="child_list_', $board['id'], '">', $txt['sub_boards'], '</strong>', implode(' ', $children), '</p>
			</div>';
	}
}

/**
 * Shows a legend for topic icons.
 */
function template_topic_legend()
{
	global $context, $settings, $txt, $modSettings;

	echo '
	<div class="tborder" id="topic_icons">
		<div class="information">
			<p id="message_index_jump_to"></p>';

	if (empty($context['no_topic_listing']))
		echo '
			<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
				<span class="main_icons profile_sm"></span> ' . $txt['participation_caption'] . '<br>' : '', '
				' . ($modSettings['pollMode'] == '1' ? '<span class="main_icons poll"></span> ' . $txt['poll'] . '<br>' : '') . '
				<span class="main_icons move"></span> ' . $txt['moved_topic'] . '<br>
			</p>
			<p>
				<span class="main_icons lock"></span> ' . $txt['locked_topic'] . '<br>
				<span class="main_icons sticky"></span> ' . $txt['sticky_topic'] . '<br>
				<span class="main_icons watch"></span> ' . $txt['watching_topic'] . '<br>
			</p>';

	if (!empty($context['jump_to']))
		echo '
			<script>
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "message_index_jump_to",
						sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', $context['jump_to']['label'], '<" + "/label> %dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						sGoButtonLabel: "', $txt['quick_mod_go'], '"
					});
			</script>';

	echo '
		</div><!-- .information -->
	</div><!-- #topic_icons -->';
}

?>