<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

/**
 * This template handles displaying a topic
 */
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Let them know, if their report was a success!
	if ($context['report_sent'])
		echo '
		<div class="infobox">
			', $txt['report_sent'], '
		</div>';

	// Let them know why their message became unapproved.
	if ($context['becomesUnapproved'])
		echo '
		<div class="noticebox">
			', $txt['post_becomes_unapproved'], '
		</div>';

	// Show new topic info here?
	echo '
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span id="top_subject">', $context['subject'], '</span>', ($context['is_locked']) ? ' <span class="main_icons lock"></span>' : '', ($context['is_sticky']) ? ' <span class="main_icons sticky"></span>' : '', '
			</h2>
			<p>', $txt['started_by'], ' ', $context['topic_poster_name'], ', ', $context['topic_started_time'], '</p>';

	// Next - Prev
	echo '
			<span class="nextlinks floatright">', $context['previous_next'], '</span>';

	if (!empty($settings['display_who_viewing']))
	{
		echo '
			<p>';

		// Show just numbers...?
		if ($settings['display_who_viewing'] == 1)
			echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
		// Or show the actual people viewing the topic?
		else
			echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) || $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

		// Now show how many guests are here too.
		echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '
			</p>';
	}

	// Show the anchor for the top and for the first message. If the first message is new, say so.
	echo '
		</div><!-- #display_head -->
		', $context['first_new_message'] ? '<a id="new"></a>' : '';

	// Is this topic also a poll?
	if ($context['is_poll'])
	{
		echo '
		<div id="poll">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="main_icons poll"></span>', $context['poll']['is_locked'] ? '<span class="main_icons lock"></span>' : '', ' ', $context['poll']['question'], '
				</h3>
			</div>
			<div class="windowbg">
				<div id="poll_options">';

		// Are they not allowed to vote but allowed to view the options?
		if ($context['poll']['show_results'] || !$context['allow_vote'])
		{
			echo '
					<dl class="options">';

			// Show each option with its corresponding percentage bar.
			foreach ($context['poll']['options'] as $option)
			{
				echo '
						<dt class="', $option['voted_this'] ? ' voted' : '', '">', $option['option'], '</dt>
						<dd class="statsbar generic_bar', $option['voted_this'] ? ' voted' : '', '">';

				if ($context['allow_results_view'])
					echo '
							', $option['bar_ndt'], '
							<span class="percentage">', $option['votes'], ' (', $option['percent'], '%)</span>';

				echo '
						</dd>';
			}

			echo '
					</dl>';

			if ($context['allow_results_view'])
				echo '
					<p><strong>', $txt['poll_total_voters'], ':</strong> ', $context['poll']['total_votes'], '</p>';
		}
		// They are allowed to vote! Go to it!
		else
		{
			echo '
					<form action="', $scripturl, '?action=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], '" method="post" accept-charset="', $context['character_set'], '">';

			// Show a warning if they are allowed more than one option.
			if ($context['poll']['allowed_warning'])
				echo '
						<p class="smallpadding">', $context['poll']['allowed_warning'], '</p>';

			echo '
						<ul class="options">';

			// Show each option with its button - a radio likely.
			foreach ($context['poll']['options'] as $option)
				echo '
							<li>', $option['vote_button'], ' <label for="', $option['id'], '">', $option['option'], '</label></li>';

			echo '
						</ul>
						<div class="submitbutton">
							<input type="submit" value="', $txt['poll_vote'], '" class="button">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						</div>
					</form>';
		}

		// Is the clock ticking?
		if (!empty($context['poll']['expire_time']))
			echo '
					<p><strong>', ($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']), ':</strong> ', $context['poll']['expire_time'], '</p>';

		echo '
				</div><!-- #poll_options -->
			</div><!-- .windowbg -->
		</div><!-- #poll -->
		<div id="pollmoderation">';

		template_button_strip($context['poll_buttons']);

		echo '
		</div>';
	}

	// Does this topic have some events linked to it?
	if (!empty($context['linked_calendar_events']))
	{
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', $txt['calendar_linked_events'], '</h3>
		</div>
		<div class="information">
			<ul>';

		foreach ($context['linked_calendar_events'] as $event)
		{
			echo '
				<li>
					<strong class="event_title"><a href="', $scripturl, '?action=calendar;event=', $event['id'], '">', $event['title'], '</a></strong>';

			if ($event['can_edit'])
				echo ' <a href="' . $event['modify_href'] . '"><span class="main_icons calendar_modify" title="', $txt['calendar_edit'], '"></span></a>';

			if ($event['can_export'])
				echo ' <a href="' . $event['export_href'] . '"><span class="main_icons calendar_export" title="', $txt['calendar_export'], '"></span></a>';

			echo '
					<br>';

			if (!empty($event['allday']))
			{
				echo '<time datetime="' . $event['start_iso_gmdate'] . '">', trim($event['start_date_local']), '</time>', ($event['start_date'] != $event['end_date']) ? ' &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">' . trim($event['end_date_local']) . '</time>' : '';
			}
			else
			{
				// Display event info relative to user's local timezone
				echo '<time datetime="' . $event['start_iso_gmdate'] . '">', trim($event['start_date_local']), ', ', trim($event['start_time_local']), '</time> &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">';

				if ($event['start_date_local'] != $event['end_date_local'])
					echo trim($event['end_date_local']) . ', ';

				echo trim($event['end_time_local']);

				// Display event info relative to original timezone
				if ($event['start_date_local'] . $event['start_time_local'] != $event['start_date_orig'] . $event['start_time_orig'])
				{
					echo '</time> (<time datetime="' . $event['start_iso_gmdate'] . '">';

					if ($event['start_date_orig'] != $event['start_date_local'] || $event['end_date_orig'] != $event['end_date_local'] || $event['start_date_orig'] != $event['end_date_orig'])
						echo trim($event['start_date_orig']), ', ';

					echo trim($event['start_time_orig']), '</time> &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">';

					if ($event['start_date_orig'] != $event['end_date_orig'])
						echo trim($event['end_date_orig']) . ', ';

					echo trim($event['end_time_orig']), ' ', $event['tz_abbrev'], '</time>)';
				}
				// Event is scheduled in the user's own timezone? Let 'em know, just to avoid confusion
				else
					echo ' ', $event['tz_abbrev'], '</time>';
			}

			if (!empty($event['location']))
				echo '
					<br>', $event['location'];

			echo '
				</li>';
		}
		echo '
			</ul>
		</div><!-- .information -->';
	}

	// Show the page index... "Pages: [1]".
	echo '
		<div class="pagesection top">
			', template_button_strip($context['normal_buttons'], 'right'), '
			', $context['menu_separator'], '
			<div class="pagelinks floatleft">
				<a href="#bot" class="button">', $txt['go_down'], '</a>
				', $context['page_index'], '
			</div>';

	// Mobile action - moderation buttons (top)
	if (!empty($context['normal_buttons']))
		echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', $txt['mobile_action'], '</a>
			', !empty($context['mod_buttons']) ? '<a class="button mobile_mod">' . $txt['mobile_moderation'] . '</a>' : '', '
		</div>';

	echo '
		</div>';

	// Show the topic information - icon, subject, etc.
	$topic_class = !empty($context['class']) ? $context['class'] : 'normal_post';
	$read_label = !empty($txt['read']) ? $txt['read'] : 'Read';
	$times_label = !empty($txt['times']) ? $txt['times'] : 'times';
	echo '
		<div id="forumposts" class="tborder">
			<h3 class="catbg3">
				<img src="', $settings['images_url'], '/topic/', $topic_class, '.gif" alt="" style="vertical-align: bottom;">
				<span>', $txt['author'], '</span>
				<span id="top_subject">', $txt['topic'], ': ', $context['subject'], !empty($context['num_views']) ? ' &nbsp;(' . $read_label . ' ' . $context['num_views'] . ' ' . $times_label . ')' : '', '</span>
			</h3>
			<form action="', $scripturl, '?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" onsubmit="return oQuickModify.bInEditMode ? oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\') : false">';

	$context['ignoredMsgs'] = array();
	$context['removableMessageIDs'] = array();

	// Get all the messages...
	while ($message = $context['get_message']())
		template_single_post($message);

	echo '
			</form>
		</div><!-- #forumposts -->';

	// Show the page index... "Pages: [1]".
	echo '
		<div class="pagesection">
			', template_button_strip($context['normal_buttons'], 'right'), '
			', $context['menu_separator'], '
			<div class="pagelinks floatleft">
				<a href="#main_content_section" class="button" id="bot">', $txt['go_up'], '</a>
				', $context['page_index'], '
			</div>';

	// Mobile action - moderation buttons (bottom)
	if (!empty($context['normal_buttons']))
		echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', $txt['mobile_action'], '</a>
			', !empty($context['mod_buttons']) ? '<a class="button mobile_mod">' . $txt['mobile_moderation'] . '</a>' : '', '
		</div>';

	echo '
		</div>';

	// Show the lower breadcrumbs.
	theme_linktree();

	// Moderation buttons
	echo '
		<div id="moderationbuttons">
			', template_button_strip($context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip')), '
		</div>';

	// Show the jumpto box, or actually...let Javascript do it.
	echo '
		<div id="display_jump_to"></div>';

	// Show quickreply
	if ($context['can_reply'])
		template_quickreply();

	// User action pop on mobile screen (or actually small screen), this uses responsive css does not check mobile device.
	echo '
		<div id="mobile_action" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					', $txt['mobile_action'], '
					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				', template_button_strip($context['normal_buttons']), '
			</div>
		</div>';

	// Show the moderation button & pop (if there is anything to show)
	if (!empty($context['mod_buttons']))
		echo '
		<div id="mobile_moderation" class="popup_container">
			<div class="popup_window description">
				<div class="popup_heading">
					', $txt['mobile_moderation'], '
					<a href="javascript:void(0);" class="main_icons hide_popup"></a>
				</div>
				<div id="moderationbuttons_mobile">
					', template_button_strip($context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip_mobile')), '
				</div>
			</div>
		</div>';

	echo '
		<script>';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $context['can_remove_post'])
	{
		echo '
			var oInTopicModeration = new InTopicModeration({
				sSelf: \'oInTopicModeration\',
				sCheckboxContainerMask: \'in_topic_mod_check_\',
				aMessageIds: [\'', implode('\', \'', $context['removableMessageIDs']), '\'],
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
				sButtonStrip: \'moderationbuttons\',
				sButtonStripDisplay: \'moderationbuttons_strip\',
				bUseImageButton: false,
				bCanRemove: ', $context['can_remove_post'] ? 'true' : 'false', ',
				sRemoveButtonLabel: \'', $txt['quickmod_delete_selected'], '\',
				sRemoveButtonImage: \'delete_selected.png\',
				sRemoveButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanRestore: ', $context['can_restore_msg'] ? 'true' : 'false', ',
				sRestoreButtonLabel: \'', $txt['quick_mod_restore'], '\',
				sRestoreButtonImage: \'restore_selected.png\',
				sRestoreButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanSplit: ', $context['can_split'] ? 'true' : 'false', ',
				sSplitButtonLabel: \'', $txt['quickmod_split_selected'], '\',
				sSplitButtonImage: \'split_selected.png\',
				sSplitButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				sFormId: \'quickModForm\'
			});';

		// Add it to the mobile button strip as well
		echo '
			var oInTopicModerationMobile = new InTopicModeration({
				sSelf: \'oInTopicModerationMobile\',
				sCheckboxContainerMask: \'in_topic_mod_check_\',
				aMessageIds: [\'', implode('\', \'', $context['removableMessageIDs']), '\'],
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
				sButtonStrip: \'moderationbuttons_mobile\',
				sButtonStripDisplay: \'moderationbuttons_strip_mobile\',
				bUseImageButton: false,
				bCanRemove: ', $context['can_remove_post'] ? 'true' : 'false', ',
				sRemoveButtonLabel: \'', $txt['quickmod_delete_selected'], '\',
				sRemoveButtonImage: \'delete_selected.png\',
				sRemoveButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanRestore: ', $context['can_restore_msg'] ? 'true' : 'false', ',
				sRestoreButtonLabel: \'', $txt['quick_mod_restore'], '\',
				sRestoreButtonImage: \'restore_selected.png\',
				sRestoreButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				bCanSplit: ', $context['can_split'] ? 'true' : 'false', ',
				sSplitButtonLabel: \'', $txt['quickmod_split_selected'], '\',
				sSplitButtonImage: \'split_selected.png\',
				sSplitButtonConfirm: \'', $txt['quickmod_confirm'], '\',
				sFormId: \'quickModForm\'
			});';
	}

	echo '
			if (\'XMLHttpRequest\' in window)
			{
				var oQuickModify = new QuickModify({
					sScriptUrl: smf_scripturl,
					sClassName: \'quick_edit\',
					bShowModify: ', $modSettings['show_modify'] ? 'true' : 'false', ',
					iTopicId: ', $context['current_topic'], ',
					sTemplateBodyEdit: ', JavaScriptEscape('
						<div id="quick_edit_body_container">
							<div id="error_box" class="error"></div>
							<textarea class="editor" name="message" rows="12" tabindex="' . $context['tabindex']++ . '">%body%</textarea><br>
							<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
							<input type="hidden" name="topic" value="' . $context['current_topic'] . '">
							<input type="hidden" name="msg" value="%msg_id%">
							<div class="righttext quickModifyMargin">
								<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\');" accesskey="s" class="button">' . ($context['show_spellchecking'] ? ' <input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \'message\');" class="button">' : '') . ' <input type="submit" name="cancel" value="' . $txt['modify_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="button">
							</div>
						</div>'), ',
					sTemplateSubjectEdit: ', JavaScriptEscape('<input type="text" name="subject" value="%subject%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '">'), ',
					sTemplateBodyNormal: ', JavaScriptEscape('%body%'), ',
					sTemplateSubjectNormal: ', JavaScriptEscape('<a href="' . $scripturl . '?topic=' . $context['current_topic'] . '.msg%msg_id%#msg%msg_id%" rel="nofollow">%subject%</a>'), ',
					sTemplateTopSubject: ', JavaScriptEscape('%subject%'), ',
					sTemplateReasonEdit: ', JavaScriptEscape($txt['reason_for_edit'] . ': <input type="text" name="modify_reason" value="%modify_reason%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '" class="quickModifyMargin">'), ',
					sTemplateReasonNormal: ', JavaScriptEscape('%modify_text'), ',
					sErrorBorderStyle: ', JavaScriptEscape('1px solid red'), ($context['can_reply']) ? ',
					sFormRemoveAccessKeys: \'postmodify\'' : '', '
				});

				aJumpTo[aJumpTo.length] = new JumpTo({
					sContainerId: "display_jump_to",
					sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', $context['jump_to']['label'], '<" + "/label> %dropdown_list%",
					iCurBoardId: ', $context['current_board'], ',
					iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
					sCurBoardName: "', $context['jump_to']['board_name'], '",
					sBoardChildLevelIndicator: "==",
					sBoardPrefix: "=> ",
					sCatSeparator: "-----------------------------",
					sCatPrefix: "",
					sGoButtonLabel: "', $txt['go'], '"
				});

				aIconLists[aIconLists.length] = new IconList({
					sBackReference: "aIconLists[" + aIconLists.length + "]",
					sIconIdPrefix: "msg_icon_",
					sScriptUrl: smf_scripturl,
					bShowModify: ', !empty($modSettings['show_modify']) ? 'true' : 'false', ',
					iBoardId: ', $context['current_board'], ',
					iTopicId: ', $context['current_topic'], ',
					sSessionId: smf_session_id,
					sSessionVar: smf_session_var,
					sLabelIconList: "', $txt['message_icon'], '",
					sBoxBackground: "transparent",
					sBoxBackgroundHover: "#ffffff",
					iBoxBorderWidthHover: 1,
					sBoxBorderColorHover: "#adadad" ,
					sContainerBackground: "#ffffff",
					sContainerBorder: "1px solid #adadad",
					sItemBorder: "1px solid #ffffff",
					sItemBorderHover: "1px dotted gray",
					sItemBackground: "transparent",
					sItemBackgroundHover: "#e0e0f0"
				});
			}';

	if (!empty($context['ignoredMsgs']))
		echo '
			ignore_toggles([', implode(', ', $context['ignoredMsgs']), '], ', JavaScriptEscape($txt['show_ignore_user_post']), ');';

	echo '
		</script>';
}

/**
 * Convert SMF 2.1 member icon markup back to the legacy Core star assets.
 *
 * The upgraded database references SMF 2.1 membericons, but this port intentionally
 * keeps the 2.0 Core visual language. Keeping the mapping in the theme template avoids
 * mutating forum data and makes the theme portable.
 */
function core21_legacy_member_icons($icons)
{
	global $settings;

	if (empty($icons))
		return '';

	$replacements = array(
		'/membericons/iconadmin.png' => '/staradmin.gif',
		'/membericons/icongmod.png' => '/stargmod.gif',
		'/membericons/iconmod.png' => '/starmod.gif',
		'/membericons/icon.png' => '/star.gif',
	);

	foreach ($replacements as $modern => $legacy)
		$icons = str_replace($settings['images_url'] . $modern, $settings['images_url'] . $legacy, $icons);

	return $icons;
}

/**
 * Hide SMF 2.1's generated default avatar so old posts match the original Core theme.
 */
function core21_legacy_avatar_image($avatar)
{
	if (empty($avatar))
		return '';

	if (strpos($avatar, '/avatars/default.png') !== false)
		return '';

	return $avatar;
}

/**
 * Use the legacy GIF post icons when the 2.1 context points at the PNG equivalents.
 */
function core21_legacy_post_icon_url($icon_url)
{
	if (empty($icon_url))
		return $icon_url;

	return preg_replace('~\.png($|[?#])~', '.gif$1', $icon_url);
}

/**
 * Template for displaying a single post in the old Core layout while using SMF 2.1 data.
 *
 * @param array $message An array of information about the message to display.
 */
function template_single_post($message)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	static $is_first_post = true;
	static $legacy_post_index = 0;

	$ignoring = false;
	$message_id = (int) $message['id'];

	if (!empty($message['can_remove']))
		$context['removableMessageIDs'][] = $message_id;

	if (!empty($message['is_ignored']))
	{
		$ignoring = true;
		$context['ignoredMsgs'][] = $message_id;
	}

	if (isset($message['approved']) && empty($message['approved']))
		$window_class = 'approvebg';
	else
		$window_class = $legacy_post_index % 2 === 0 ? 'windowbg' : 'windowbg2';

	$legacy_post_index++;
	$post_classes = trim('clearfix ' . (!$is_first_post ? 'topborder ' : '') . $window_class . ' largepadding');
	$reply_label = !empty($message['counter']) ? ((!empty($txt['reply_noun']) ? $txt['reply_noun'] : 'Reply') . ' #' . $message['counter']) : '';
	$on_label = !empty($txt['on']) ? $txt['on'] : 'on';
	$avatar = core21_legacy_avatar_image(!empty($message['member']['avatar']['image']) ? $message['member']['avatar']['image'] : '');
	$group_icons = core21_legacy_member_icons(!empty($message['member']['group_icons']) ? $message['member']['group_icons'] : '');
	$icon_url = core21_legacy_post_icon_url(!empty($message['icon_url']) ? $message['icon_url'] : $settings['images_url'] . '/post/xx.gif');

	echo '
				<div class="bordercolor">
					<a id="msg', $message_id, '"></a>', !empty($message['first_new']) ? '<a id="new"></a>' : '', '
					<div class="', $post_classes, '">';

	if ($ignoring)
		echo '
						<div class="ignored" id="msg_', $message_id, '_ignored_prompt">
							', $txt['ignoring_user'], '
							<a href="#" id="msg_', $message_id, '_ignored_link" style="display: none;">', $txt['show_ignore_user_post'], '</a>
						</div>';

	// Poster column.
	echo '
						<div class="floatleft poster">
							<h4>', $message['member']['link'], '</h4>
							<ul class="reset smalltext" id="msg_', $message_id, '_extra_info">';

	if (!empty($message['member']['title']))
		echo '
								<li>', $message['member']['title'], '</li>';

	if (!empty($message['member']['group']))
		echo '
								<li>', $message['member']['group'], '</li>';

	if (empty($message['member']['is_guest']))
	{
		if ((empty($modSettings['hide_post_group']) || empty($message['member']['group'])) && !empty($message['member']['post_group']))
			echo '
								<li>', $message['member']['post_group'], '</li>';

		if (!empty($group_icons))
			echo '
								<li>', $group_icons, '</li>';

		if (!isset($context['disabled_fields']['posts']))
			echo '
								<li>', $txt['member_postcount'], ': ', $message['member']['posts'], '</li>';

		if (!empty($modSettings['show_blurb']) && !empty($message['member']['blurb']))
		{
			// In the original Core ordering, avatars appear before the personal text.
			if (!empty($modSettings['show_user_images']) && empty($options['show_no_avatars']) && !empty($avatar))
				echo '
								<li class="margintop" style="overflow: auto;">', $avatar, '</li>';

			echo '
								<li class="margintop">', $message['member']['blurb'], '</li>';
		}
		elseif (!empty($modSettings['show_user_images']) && empty($options['show_no_avatars']) && !empty($avatar))
			echo '
								<li class="margintop" style="overflow: auto;">', $avatar, '</li>';

		if (!empty($message['custom_fields']['standard']))
			foreach ($message['custom_fields']['standard'] as $custom)
				if (!empty($custom['value']))
					echo '
								<li>', !empty($custom['title']) ? $custom['title'] . ': ' : '', $custom['value'], '</li>';

		if (!empty($message['custom_fields']['icons']))
		{
			$shown = false;
			foreach ($message['custom_fields']['icons'] as $custom)
			{
				if (empty($custom['value']))
					continue;

				if (!$shown)
				{
					$shown = true;
					echo '
								<li class="margintop">
									<ul class="reset nolist">';
				}

				echo '
										<li>', $custom['value'], '</li>';
			}

			if ($shown)
				echo '
									</ul>
								</li>';
		}

		// Old Core always showed the small profile icon when the profile was visible.
		if (!empty($message['member']['href']))
		{
			echo '
								<li class="margintop">
									<ul class="reset nolist">
										<li><a href="', $message['member']['href'], '"><img src="', $settings['images_url'], '/icons/profile_sm.gif" alt="', $txt['view_profile'], '" title="', $txt['view_profile'], '" border="0" /></a></li>';

			if (!empty($message['member']['website']['url']))
				echo '
										<li><a href="', $message['member']['website']['url'], '" title="', $message['member']['website']['title'], '" target="_blank" rel="noopener" class="new_win"><img src="', $settings['images_url'], '/www_sm.gif" alt="', $message['member']['website']['title'], '" border="0" /></a></li>';

			if (!empty($context['can_send_pm']))
				echo '
										<li><a href="', $scripturl, '?action=pm;sa=send;u=', $message['member']['id'], '" title="', !empty($message['member']['online']['is_online']) ? $txt['pm_online'] : $txt['pm_offline'], '"><img src="', $settings['images_url'], '/im_', !empty($message['member']['online']['is_online']) ? 'on' : 'off', '.gif" alt="', !empty($message['member']['online']['is_online']) ? $txt['pm_online'] : $txt['pm_offline'], '" border="0" /></a></li>';

			echo '
									</ul>
								</li>';
		}

		if (!empty($message['member']['can_see_warning']))
			echo '
								<li class="warning">', !empty($context['can_issue_warning']) ? '<a href="' . $scripturl . '?action=profile;area=issuewarning;u=' . $message['member']['id'] . '">' : '', '<span class="main_icons warning_' . $message['member']['warning_status'] . '"></span> ', !empty($context['can_issue_warning']) ? '</a>' : '', '<span class="warn_' . $message['member']['warning_status'] . '">' . $txt['warn_' . $message['member']['warning_status']] . '</span></li>';
	}
	elseif (!empty($message['member']['email']) && !empty($message['member']['show_email']))
		echo '
								<li><a href="mailto:', $message['member']['email'], '" rel="nofollow"><img src="', $settings['images_url'], '/email_sm.gif" alt="', $txt['email'], '" title="', $txt['email'], '" border="0" /></a></li>';

	echo '
							</ul>
						</div>
						<div class="postarea">
							<div class="flow_hidden">
								<div class="keyinfo">
									<div class="messageicon"><img src="', $icon_url, '" alt="" border="0"', !empty($message['can_modify']) ? ' id="msg_icon_' . $message_id . '"' : '', ' /></div>
									<h5 id="subject_', $message_id, '">
										<a href="', $message['href'], '" rel="nofollow">', $message['subject'], '</a>
									</h5>
									<div class="smalltext">&#171; <strong>', $reply_label, ' ', $on_label, ':</strong> ', $message['time'], ' &#187;</div>
									<div id="msg_', $message_id, '_quick_mod"></div>
								</div>';

	if (!empty($message['quickbuttons']))
	{
		echo '
								<div class="core21_post_buttons">';
		template_quickbuttons($message['quickbuttons'], 'post');
		echo '
								</div>';
	}

	echo '
							</div>
							<div class="post">
								<hr class="hrcolor" width="100%" size="1" />';

	if (empty($message['approved']) && !empty($message['member']['id']) && $message['member']['id'] == $context['user']['id'])
		echo '
								<div class="approve_post">
									', $txt['post_awaiting_approval'], '
								</div>';

	echo '
								<div class="inner" data-msgid="', $message_id, '" id="msg_', $message_id, '"', $ignoring ? ' style="display:none;"' : '', '>', $message['body'], '</div>
							</div>';

	if (!empty($message['can_modify']))
		echo '
							<img src="', $settings['images_url'], '/icons/modify_inline.gif" alt="', $txt['modify_msg'], '" title="', $txt['modify_msg'], '" class="modifybutton" id="modify_button_', $message_id, '" style="cursor: pointer; display: none;" onclick="oQuickModify.modifyMsg(\'', $message_id, '\')" />';

	// Attachments.
	if (!empty($message['attachment']))
	{
		echo '
							<div id="msg_', $message_id, '_footer" class="attachments smalltext"', $ignoring ? ' style="display:none;"' : '', '>';

		$last_approved_state = 1;
		foreach ($message['attachment'] as $attachment)
		{
			if (isset($attachment['is_approved']) && $attachment['is_approved'] != $last_approved_state)
			{
				$last_approved_state = 0;
				echo '
								<fieldset>
									<legend>', $txt['attach_awaiting_approve'];

				if (!empty($context['can_approve']))
					echo '&nbsp;[<a href="', $scripturl, '?action=attachapprove;sa=all;mid=', $message_id, ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve_all'], '</a>]';

				echo '</legend>';
			}

			if (!empty($attachment['is_image']))
			{
				if (!empty($attachment['thumbnail']['has_thumb']))
					echo '
								<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" onclick="', $attachment['thumbnail']['javascript'], '"><img src="', $attachment['thumbnail']['href'], '" alt="" id="thumb_', $attachment['id'], '" border="0" /></a><br />';
				else
					echo '
								<img src="', $attachment['href'], ';image" alt="" loading="lazy" border="0" /><br />';
			}

			echo '
								<a href="', $attachment['href'], '"><img src="', $settings['images_url'], '/icons/clip.gif" align="middle" alt="*" border="0" />&nbsp;', $attachment['name'], '</a> ';

			if (empty($attachment['is_approved']) && !empty($context['can_approve']))
				echo '
								[<a href="', $scripturl, '?action=attachapprove;sa=approve;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve'], '</a>]&nbsp;|&nbsp;[<a href="', $scripturl, '?action=attachapprove;sa=reject;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['delete'], '</a>] ';

			echo '
								(', !empty($attachment['size']) ? $attachment['size'] : '', (!empty($attachment['is_image']) && isset($attachment['real_width'], $attachment['real_height']) ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] : ''), !empty($attachment['downloads']) ? ' - ' . sprintf(!empty($attachment['is_image']) ? $txt['attach_viewed'] : $txt['attach_downloaded'], $attachment['downloads']) : '', ')<br />';
		}

		if ($last_approved_state == 0)
			echo '
								</fieldset>';

		echo '
							</div>';
	}

	echo '
						</div>
						<div class="moderatorbar">
							<div class="smalltext floatleft" id="modified_', $message_id, '">';

	if ((!empty($modSettings['show_modify']) || !empty($settings['show_modify'])) && !empty($message['modified']['name']))
		echo !empty($message['modified']['last_edit_text']) ? $message['modified']['last_edit_text'] : '&#171; <em>' . $txt['last_edit'] . ': ' . $message['modified']['time'] . ' ' . $txt['by'] . ' ' . $message['modified']['name'] . '</em> &#187;';

	echo '
							</div>
							<div class="smalltext largepadding floatright">';

	if (!empty($context['can_report_moderator']))
		echo '
								<a href="', $scripturl, '?action=reporttm;topic=', $context['current_topic'], '.', !empty($message['counter']) ? $message['counter'] : 0, ';msg=', $message_id, '">', $txt['report_to_mod'], '</a> &nbsp;';

	if (!empty($context['can_issue_warning']) && empty($message['is_message_author']) && empty($message['member']['is_guest']))
		echo '
								<a href="', $scripturl, '?action=profile;area=issuewarning;u=', $message['member']['id'], ';msg=', $message_id, '"><span class="main_icons warning_moderate" title="', $txt['issue_warning_post'], '"></span></a>';

	echo '
								<img src="', $settings['images_url'], '/ip.gif" alt="" border="0" />';

	if (!empty($context['can_moderate_forum']) && !empty($message['member']['ip']))
		echo '
								<a href="', $scripturl, '?action=', !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'], ';searchip=', $message['member']['ip'], '">', $message['member']['ip'], '</a> <a href="', $scripturl, '?action=helpadmin;help=see_admin_ip" onclick="return reqOverlayDiv(this.href);" class="help">(?)</a>';
	elseif (!empty($message['can_see_ip']))
		echo '
								<a href="', $scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help">', $message['member']['ip'], '</a>';
	elseif (empty($context['user']['is_guest']))
		echo '
								<a href="', $scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqOverlayDiv(this.href);" class="help">', $txt['logged'], '</a>';
	else
		echo '
								', $txt['logged'];

	echo '
							</div>';

	if (!empty($message['custom_fields']['above_signature']))
	{
		echo '
							<div class="custom_fields_above_signature">
								<ul class="reset nolist">';

		foreach ($message['custom_fields']['above_signature'] as $custom)
			if (!empty($custom['value']))
				echo '
									<li>', $custom['value'], '</li>';

		echo '
								</ul>
							</div>';
	}

	if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && !empty($context['signature_enabled']))
		echo '
							<div class="signature" id="msg_', $message_id, '_signature"', $ignoring ? ' style="display:none;"' : '', '>', $message['member']['signature'], '</div>';

	if (!empty($message['custom_fields']['below_signature']))
	{
		echo '
							<div class="custom_fields_below_signature">
								<ul class="reset nolist">';

		foreach ($message['custom_fields']['below_signature'] as $custom)
			if (!empty($custom['value']))
				echo '
									<li>', $custom['value'], '</li>';

		echo '
								</ul>
							</div>';
	}

	echo '
						</div>
					</div>
				</div>';

	$is_first_post = false;
}

/**
 * The template for displaying the quick reply box.
 */
function template_quickreply()
{
	global $context, $modSettings, $scripturl, $options, $txt;

	echo '
		<a id="quickreply_anchor"></a>
		<div class="tborder" id="quickreply">
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['quick_reply'], '
				</h3>
			</div>
			<div id="quickreply_options">
				<div class="roundframe">';

	// Is the topic locked?
	if ($context['is_locked'])
		echo '
					<p class="alert smalltext">', $txt['quick_reply_warning'], '</p>';

	// Show a warning if the topic is old
	if (!empty($context['oldTopicError']))
		echo '
					<p class="alert smalltext">', sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']), '</p>';

	// Does the post need approval?
	if (!$context['can_reply_approved'])
		echo '
					<p><em>', $txt['wait_for_approval'], '</em></p>';

	echo '
					<form action="', $scripturl, '?board=', $context['current_board'], ';action=post2" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" onsubmit="submitonce(this);">
						<input type="hidden" name="topic" value="', $context['current_topic'], '">
						<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '">
						<input type="hidden" name="icon" value="xx">
						<input type="hidden" name="from_qr" value="1">
						<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '">
						<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '">
						<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '">
						<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">';

	// Guests just need more.
	if ($context['user']['is_guest'])
	{
		echo '
						<dl id="post_header">
							<dt>
								', $txt['name'], ':
							</dt>
							<dd>
								<input type="text" name="guestname" size="25" value="', $context['name'], '" tabindex="', $context['tabindex']++, '" required>
							</dd>';

		if (empty($modSettings['guest_post_no_email']))
		{
			echo '
							<dt>
								', $txt['email'], ':
							</dt>
							<dd>
								<input type="email" name="email" size="25" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" required>
							</dd>';
		}

		echo '
						</dl>';
	}

	echo '
						', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
						<script>
							function insertQuoteFast(messageid)
							{
								var e = document.getElementById("', $context['post_box_name'], '");
								sceditor.instance(e).insertQuoteFast(messageid);

								return false;
							}
						</script>';

	// Is visual verification enabled?
	if ($context['require_verification'])
		echo '
						<div class="post_verification">
							<strong>', $txt['verification'], ':</strong>
							', template_control_verification($context['visual_verification_id'], 'all'), '
						</div>';

	// Finally, the submit buttons.
	echo '
						<span id="post_confirm_buttons">
							', template_control_richedit_buttons($context['post_box_name']), '
						</span>';
	echo '
					</form>
				</div><!-- .roundframe -->
			</div><!-- #quickreply_options -->
		</div><!-- #quickreply -->
		<br class="clear">';

	// Draft autosave available and the user has it enabled?
	if (!empty($context['drafts_autosave']))
		echo '
		<script>
			var oDraftAutoSave = new smf_DraftAutoSave({
				sSelf: \'oDraftAutoSave\',
				sLastNote: \'draft_lastautosave\',
				sLastID: \'id_draft\',', !empty($context['post_box_name']) ? '
				sSceditorID: \'' . $context['post_box_name'] . '\',' : '', '
				sType: \'', 'quick', '\',
				iBoard: ', (empty($context['current_board']) ? 0 : $context['current_board']), ',
				iFreq: ', (empty($modSettings['masterAutoSaveDraftsDelay']) ? 60000 : $modSettings['masterAutoSaveDraftsDelay'] * 1000), '
			});
		</script>';

	if ($context['show_spellchecking'])
		echo '
		<form action="', $scripturl, '?action=spellcheck" method="post" accept-charset="', $context['character_set'], '" name="spell_form" id="spell_form" target="spellWindow">
			<input type="hidden" name="spellstring" value="">
		</form>';

	echo '
		<script>
			var oQuickReply = new QuickReply({
				bDefaultCollapsed: false,
				iTopicId: ', $context['current_topic'], ',
				iStart: ', $context['start'], ',
				sScriptUrl: smf_scripturl,
				sImagesUrl: smf_images_url,
				sContainerId: "quickreply_options",
				sImageId: "quickReplyExpand",
				sClassCollapsed: "toggle_up",
				sClassExpanded: "toggle_down",
				sJumpAnchor: "quickreply_anchor",
				bIsFull: true
			});
			var oEditorID = "', $context['post_box_name'], '";
			var oEditorObject = oEditorHandle_', $context['post_box_name'], ';
			var oJumpAnchor = "quickreply_anchor";
		</script>';
}

?>