<?php

/**
 * Google ad integrations for IronNoob's SMF forum.
 *
 * Adds Google-provided scripts to every rendered SMF page through standard SMF
 * hooks, avoiding direct theme template edits and keeping public IDs editable
 * from General Mod Settings.
 */

if (!defined('SMF'))
	die('No direct access...');

class IronNoobAnalytics
{
	private const DEFAULT_MEASUREMENT_ID = 'G-8917501KJP';
	private const DEFAULT_ADSENSE_CLIENT_ID = 'ca-pub-5952965523717725';

	public static function install(): void
	{
		self::ensureDefaultSettings();

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
		self::ensureDefaultSettings();

		$config_vars[] = '';
		$config_vars[] = array('title', 'ironnoob_ga_title');
		$config_vars[] = array('desc', 'ironnoob_ga_desc');
		$config_vars[] = array('check', 'ironnoob_ga_enabled');
		$config_vars[] = array('text', 'ironnoob_ga_measurement_id', 20);

		$config_vars[] = '';
		$config_vars[] = array('title', 'ironnoob_adsense_title');
		$config_vars[] = array('desc', 'ironnoob_adsense_desc');
		$config_vars[] = array('check', 'ironnoob_adsense_enabled');
		$config_vars[] = array('text', 'ironnoob_adsense_client_id', 24);
	}

	public static function saveSettings(array &$save_vars): void
	{
		if (isset($_POST['ironnoob_ga_measurement_id']))
			$_POST['ironnoob_ga_measurement_id'] = strtoupper(trim((string) $_POST['ironnoob_ga_measurement_id']));
		if (isset($_POST['ironnoob_adsense_client_id']))
			$_POST['ironnoob_adsense_client_id'] = strtolower(trim((string) $_POST['ironnoob_adsense_client_id']));
	}

	public static function loadTheme(): void
	{
		global $context;

		if (isset($_REQUEST['xml']))
			return;

		if (!isset($context['html_headers']))
			$context['html_headers'] = '';

		self::addGoogleAnalyticsHeader();
		self::addAdSenseHeader();
	}

	private static function addGoogleAnalyticsHeader(): void
	{
		global $context, $modSettings;

		if (empty($modSettings['ironnoob_ga_enabled']))
			return;

		$measurementId = !empty($modSettings['ironnoob_ga_measurement_id'])
			? strtoupper((string) $modSettings['ironnoob_ga_measurement_id'])
			: self::DEFAULT_MEASUREMENT_ID;

		// Google Analytics 4 measurement IDs are public and currently use G- prefixes.
		if (!preg_match('/^G-[A-Z0-9]+$/', $measurementId))
			return;

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

	private static function addAdSenseHeader(): void
	{
		global $context, $modSettings;

		if (isset($modSettings['ironnoob_adsense_enabled']) && empty($modSettings['ironnoob_adsense_enabled']))
			return;

		$clientId = !empty($modSettings['ironnoob_adsense_client_id'])
			? strtolower(trim((string) $modSettings['ironnoob_adsense_client_id']))
			: self::DEFAULT_ADSENSE_CLIENT_ID;

		// Google AdSense publisher client IDs are public and use ca-pub- plus 16 digits.
		if (!preg_match('/^ca-pub-\d{16}$/', $clientId))
			return;

		// Avoid duplicates if another integration or a future theme edit adds the same tag.
		if (strpos($context['html_headers'], 'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . $clientId) !== false)
			return;

		$escapedClientId = htmlspecialchars($clientId, ENT_QUOTES, 'UTF-8');

		$context['html_headers'] .= <<<HTML

	<!-- Google AdSense -->
	<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={$escapedClientId}"
	     crossorigin="anonymous"></script>
HTML;
	}

	private static function ensureDefaultSettings(): void
	{
		global $modSettings;

		$defaults = array();
		if (!isset($modSettings['ironnoob_ga_enabled']))
			$defaults['ironnoob_ga_enabled'] = '1';
		if (!isset($modSettings['ironnoob_ga_measurement_id']))
			$defaults['ironnoob_ga_measurement_id'] = self::DEFAULT_MEASUREMENT_ID;
		if (!isset($modSettings['ironnoob_adsense_enabled']))
			$defaults['ironnoob_adsense_enabled'] = '1';
		if (!isset($modSettings['ironnoob_adsense_client_id']))
			$defaults['ironnoob_adsense_client_id'] = self::DEFAULT_ADSENSE_CLIENT_ID;

		if (empty($defaults))
			return;

		updateSettings($defaults);
		$modSettings = array_merge($modSettings, $defaults);
	}

	private static function loadText(): void
	{
		global $txt;

		$txt['ironnoob_ga_title'] = 'Google Analytics';
		$txt['ironnoob_ga_desc'] = 'Adds the Google tag to all normal SMF-rendered pages.';
		$txt['ironnoob_ga_enabled'] = 'Enable Google Analytics tag';
		$txt['ironnoob_ga_measurement_id'] = 'GA4 measurement ID';
		$txt['ironnoob_adsense_title'] = 'Google AdSense';
		$txt['ironnoob_adsense_desc'] = 'Adds the Google AdSense verification/loader tag to all normal SMF-rendered pages.';
		$txt['ironnoob_adsense_enabled'] = 'Enable Google AdSense tag';
		$txt['ironnoob_adsense_client_id'] = 'AdSense publisher client ID';
	}
}
