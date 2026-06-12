<?php

/**
 * Google Analytics integration for IronNoob's SMF forum.
 *
 * Adds the Google tag to every rendered SMF page through standard SMF hooks,
 * avoiding direct theme template edits and keeping the measurement ID editable
 * from General Mod Settings.
 */

if (!defined('SMF'))
	die('No direct access...');

class IronNoobAnalytics
{
	private const DEFAULT_MEASUREMENT_ID = 'G-8917501KJP';

	public static function install(): void
	{
		global $modSettings;

		$defaults = array();
		if (!isset($modSettings['ironnoob_ga_enabled']))
			$defaults['ironnoob_ga_enabled'] = '1';
		if (!isset($modSettings['ironnoob_ga_measurement_id']))
			$defaults['ironnoob_ga_measurement_id'] = self::DEFAULT_MEASUREMENT_ID;

		if (!empty($defaults))
			updateSettings($defaults);

		add_integration_function('integrate_load_theme', 'IronNoobAnalytics::loadTheme', true, '$sourcedir/IronNoobAnalytics.php');
		add_integration_function('integrate_general_mod_settings', 'IronNoobAnalytics::settings', true, '$sourcedir/IronNoobAnalytics.php');
		add_integration_function('integrate_save_general_mod_settings', 'IronNoobAnalytics::saveSettings', true, '$sourcedir/IronNoobAnalytics.php');
	}

	public static function uninstall(): void
	{
		remove_integration_function('integrate_load_theme', 'IronNoobAnalytics::loadTheme', true, '$sourcedir/IronNoobAnalytics.php');
		remove_integration_function('integrate_general_mod_settings', 'IronNoobAnalytics::settings', true, '$sourcedir/IronNoobAnalytics.php');
		remove_integration_function('integrate_save_general_mod_settings', 'IronNoobAnalytics::saveSettings', true, '$sourcedir/IronNoobAnalytics.php');
	}

	public static function settings(array &$config_vars): void
	{
		self::loadText();

		$config_vars[] = '';
		$config_vars[] = array('title', 'ironnoob_ga_title');
		$config_vars[] = array('desc', 'ironnoob_ga_desc');
		$config_vars[] = array('check', 'ironnoob_ga_enabled');
		$config_vars[] = array('text', 'ironnoob_ga_measurement_id', 20);
	}

	public static function saveSettings(array &$save_vars): void
	{
		if (isset($_POST['ironnoob_ga_measurement_id']))
			$_POST['ironnoob_ga_measurement_id'] = strtoupper(trim((string) $_POST['ironnoob_ga_measurement_id']));
	}

	public static function loadTheme(): void
	{
		global $context, $modSettings;

		if (isset($_REQUEST['xml']) || empty($modSettings['ironnoob_ga_enabled']))
			return;

		$measurementId = !empty($modSettings['ironnoob_ga_measurement_id'])
			? strtoupper((string) $modSettings['ironnoob_ga_measurement_id'])
			: self::DEFAULT_MEASUREMENT_ID;

		// Google Analytics 4 measurement IDs are public and currently use G- prefixes.
		if (!preg_match('/^G-[A-Z0-9]+$/', $measurementId))
			return;

		if (!isset($context['html_headers']))
			$context['html_headers'] = '';

		// Avoid duplicates if another integration or a future theme edit adds the same tag.
		if (strpos($context['html_headers'], 'googletagmanager.com/gtag/js?id=' . $measurementId) !== false)
			return;

		$escapedId = htmlspecialchars($measurementId, ENT_QUOTES, 'UTF-8');

		$context['html_headers'] .= <<<HTML

	<!-- Google tag (gtag.js) -->
	<script async src="https://www.googletagmanager.com/gtag/js?id={$escapedId}"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', '{$escapedId}');
	</script>
HTML;
	}

	private static function loadText(): void
	{
		global $txt;

		$txt['ironnoob_ga_title'] = 'Google Analytics';
		$txt['ironnoob_ga_desc'] = 'Adds the Google tag to all normal SMF-rendered pages.';
		$txt['ironnoob_ga_enabled'] = 'Enable Google Analytics tag';
		$txt['ironnoob_ga_measurement_id'] = 'GA4 measurement ID';
	}
}
