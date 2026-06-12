<?php

/**
 * Conservative security guardrails for IronNoob's SMF forum.
 *
 * This intentionally avoids invasive SMF core changes. It adds browser/session
 * hardening headers and disables unused high-risk surfaces through normal SMF
 * integration hooks, with admin-controllable settings under General Mod Settings.
 */

if (!defined('SMF'))
	die('No direct access...');

class IronNoobSecurity
{
	private const SETTINGS = array(
		'ironnoob_security_enabled' => '1',
		'ironnoob_security_headers' => '1',
		'ironnoob_security_secure_sessions' => '1',
		'ironnoob_security_hsts' => '1',
		'ironnoob_security_block_package_manager' => '1',
		'ironnoob_security_block_attachments' => '1',
	);

	/**
	 * Register persistent SMF integration hooks and seed default settings.
	 */
	public static function install(): void
	{
		global $modSettings;

		$defaults = array();
		foreach (self::SETTINGS as $setting => $default)
		{
			if (!isset($modSettings[$setting]))
				$defaults[$setting] = $default;
		}

		if (!empty($defaults))
			updateSettings($defaults);

		self::addHook('integrate_pre_load', 'IronNoobSecurity::preLoad');
		self::addHook('integrate_actions', 'IronNoobSecurity::actions');
		self::addHook('integrate_load_theme', 'IronNoobSecurity::loadTheme');
		self::addHook('integrate_general_mod_settings', 'IronNoobSecurity::settings');
		self::addHook('integrate_save_general_mod_settings', 'IronNoobSecurity::saveSettings');
	}

	/**
	 * Remove persistent hooks. Settings are intentionally preserved.
	 */
	public static function uninstall(): void
	{
		self::removeHook('integrate_pre_load', 'IronNoobSecurity::preLoad');
		self::removeHook('integrate_actions', 'IronNoobSecurity::actions');
		self::removeHook('integrate_load_theme', 'IronNoobSecurity::loadTheme');
		self::removeHook('integrate_general_mod_settings', 'IronNoobSecurity::settings');
		self::removeHook('integrate_save_general_mod_settings', 'IronNoobSecurity::saveSettings');
	}

	/**
	 * Runs very early, before PHP sessions are started.
	 */
	public static function preLoad(): void
	{
		if (!self::enabled())
			return;

		if (self::setting('ironnoob_security_secure_sessions'))
			self::hardenPhpRuntime();

		if (self::setting('ironnoob_security_headers'))
			self::sendSecurityHeaders();

		if (self::setting('ironnoob_security_block_package_manager') && self::isPackageManagerRequest())
			self::denyEarly('Package Manager is disabled by IronNoob Security Guard. Disable this guardrail temporarily in General Mod Settings before installing or removing packages.');
	}

	/**
	 * Replace unused risky actions with a hard 403 while preserving all normal forum actions.
	 */
	public static function actions(array &$actionArray): void
	{
		if (!self::enabled() || !self::setting('ironnoob_security_block_attachments'))
			return;

		$actionArray['dlattach'] = array('IronNoobSecurity.php', 'IronNoobSecurity::blockedAction');
		$actionArray['uploadAttach'] = array('IronNoobSecurity.php', 'IronNoobSecurity::blockedAction');
	}

	/**
	 * Add language text for admin settings pages.
	 */
	public static function loadTheme(): void
	{
		if (self::enabled())
			self::allowInitialGuestSessionPersistence();

		self::loadText();
	}

	/**
	 * Add settings to Admin > Configuration > Features and Options > General Mod Settings.
	 */
	public static function settings(array &$config_vars): void
	{
		self::loadText();

		$config_vars[] = '';
		$config_vars[] = array('title', 'ironnoob_security_title');
		$config_vars[] = array('desc', 'ironnoob_security_desc');
		$config_vars[] = array('check', 'ironnoob_security_enabled');
		$config_vars[] = array('check', 'ironnoob_security_headers');
		$config_vars[] = array('check', 'ironnoob_security_secure_sessions');
		$config_vars[] = array('check', 'ironnoob_security_hsts');
		$config_vars[] = array('check', 'ironnoob_security_block_package_manager');
		$config_vars[] = array('check', 'ironnoob_security_block_attachments');
	}

	/**
	 * General Mod Settings hook. Boolean settings are handled by SMF.
	 */
	public static function saveSettings(array &$save_vars): void
	{
		// Intentionally empty: all current settings are booleans managed by SMF.
	}

	/**
	 * Standard blocked action handler used after the normal SMF dispatcher starts.
	 */
	public static function blockedAction(): void
	{
		self::denyEarly('This endpoint is disabled by IronNoob Security Guard.');
	}

	private static function addHook(string $hook, string $function): void
	{
		add_integration_function($hook, $function, true, '$sourcedir/IronNoobSecurity.php');
	}

	private static function removeHook(string $hook, string $function): void
	{
		remove_integration_function($hook, $function, true, '$sourcedir/IronNoobSecurity.php');
	}

	private static function enabled(): bool
	{
		global $boarddir;

		// Emergency kill switch if a future change ever misbehaves.
		if (!empty($boarddir) && file_exists($boarddir . '/.ironnoob-security-disabled'))
			return false;

		return self::setting('ironnoob_security_enabled');
	}

	private static function setting(string $name): bool
	{
		global $modSettings;

		if (!array_key_exists($name, self::SETTINGS))
			return false;

		return !empty($modSettings[$name]) || (!isset($modSettings[$name]) && self::SETTINGS[$name] === '1');
	}

	private static function hardenPhpRuntime(): void
	{
		// These are effective before SMF calls loadSession().
		@ini_set('session.use_only_cookies', '1');
		@ini_set('session.use_strict_mode', '1');
		@ini_set('session.use_trans_sid', '0');
		@ini_set('session.cookie_httponly', '1');
		@ini_set('session.cookie_samesite', 'Lax');

		if (self::isHttps())
			@ini_set('session.cookie_secure', '1');

		// Avoid leaking PHP warnings/notices to visitors if cPanel toggles are loosened later.
		@ini_set('display_errors', '0');
		@ini_set('log_errors', '1');
	}

	/**
	 * SMF's database session handler skips writes when the incoming request has
	 * no cookies. That is normally harmless, but it breaks first-visit guest
	 * forms that create one-time tokens, such as password reset pages opened
	 * directly from email: PHP sends the new session cookie to the browser, but
	 * the token-bearing session row is never saved for the follow-up POST.
	 *
	 * Once PHP has accepted/created a session ID, mirror that ID into the current
	 * request cookie array so SMF will persist the initial token state at request
	 * shutdown. This does not send any extra cookie; PHP already handles that.
	 */
	private static function allowInitialGuestSessionPersistence(): void
	{
		global $modSettings;

		if (PHP_SAPI === 'cli' || empty($modSettings['databaseSession_enable']) || session_status() !== PHP_SESSION_ACTIVE)
			return;

		$sessionName = session_name();
		$sessionId = session_id();

		if ($sessionName === '' || $sessionId === '' || isset($_COOKIE[$sessionName]))
			return;

		$_COOKIE[$sessionName] = $sessionId;
	}

	private static function sendSecurityHeaders(): void
	{
		if (headers_sent())
			return;

		// Suppress PHP version disclosure from the X-Powered-By header when allowed by SAPI.
		@header_remove('X-Powered-By');

		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: strict-origin-when-cross-origin');
		header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
		header('X-Permitted-Cross-Domain-Policies: none');

		if (self::setting('ironnoob_security_hsts') && self::isHttps())
			header('Strict-Transport-Security: max-age=31536000');
	}

	private static function isPackageManagerRequest(): bool
	{
		$params = array();
		if (!empty($_SERVER['QUERY_STRING']))
			parse_str(str_replace(';', '&', (string) $_SERVER['QUERY_STRING']), $params);

		$action = isset($params['action']) ? (string) $params['action'] : (isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '');
		$area = isset($params['area']) ? (string) $params['area'] : (isset($_REQUEST['area']) ? (string) $_REQUEST['area'] : '');

		return $action === 'admin' && $area === 'packages';
	}

	private static function isHttps(): bool
	{
		if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
			return true;

		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
			return true;

		if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos((string) $_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
			return true;

		return false;
	}

	private static function denyEarly(string $message): void
	{
		if (!headers_sent())
		{
			if (function_exists('send_http_status'))
				send_http_status(403);
			else
				header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');

			header('Content-Type: text/plain; charset=UTF-8');
		}

		die($message);
	}

	private static function loadText(): void
	{
		global $txt;

		$txt['ironnoob_security_title'] = 'IronNoob Security Guard';
		$txt['ironnoob_security_desc'] = 'Adds conservative browser/session hardening and disables unused high-risk SMF surfaces. Emergency disable: create a file named .ironnoob-security-disabled in the forum root.';
		$txt['ironnoob_security_enabled'] = 'Enable IronNoob Security Guard';
		$txt['ironnoob_security_headers'] = 'Send additional security headers';
		$txt['ironnoob_security_secure_sessions'] = 'Harden PHP session cookie/runtime settings';
		$txt['ironnoob_security_hsts'] = 'Send HSTS for HTTPS requests';
		$txt['ironnoob_security_block_package_manager'] = 'Disable SMF Package Manager web UI';
		$txt['ironnoob_security_block_attachments'] = 'Disable attachment download/upload endpoints';
	}
}
