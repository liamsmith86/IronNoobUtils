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
		self::addHook('integrate_exit', 'IronNoobSecurity::finalize');
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
		self::removeHook('integrate_exit', 'IronNoobSecurity::finalize');
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
		{
			self::discardInvalidIncomingSessionCookie();
			self::hardenPhpRuntime();
		}

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
			self::stabilizeDatabaseSession();

		self::loadText();
	}

	/**
	 * Runs shortly before SMF flushes buffered output.
	 */
	public static function finalize(bool $doFooter): void
	{
		if (self::enabled())
			self::sendSensitiveFormCacheHeaders();
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

	private static function discardInvalidIncomingSessionCookie(): void
	{
		$sessionName = session_name();
		if ($sessionName === '' || empty($_COOKIE[$sessionName]) || self::isValidSessionId((string) $_COOKIE[$sessionName]))
			return;

		unset($_COOKIE[$sessionName], $_REQUEST[$sessionName], $_GET[$sessionName], $_POST[$sessionName]);

		if (!headers_sent())
		{
			@setcookie($sessionName, '', self::sessionCookieOptions(time() - 3600));
		}
	}

	/**
	 * SMF's database session handler skips writes when the incoming request has
	 * no cookies. That is normally harmless, but it breaks first-visit guest
	 * forms that create one-time tokens, such as password reset pages opened
	 * directly from email: PHP sends the new session cookie to the browser, but
	 * the token-bearing session row is never saved for the follow-up POST.
	 *
	 * Bad or stale PHPSESSID cookies can also leave SMF with an invalid session
	 * ID that will not be written by its database handler. Normalize that early,
	 * then mirror the active ID into the current request cookie array so SMF will
	 * persist the token state at request shutdown. PHP still owns the actual
	 * Set-Cookie header.
	 */
	private static function stabilizeDatabaseSession(): void
	{
		global $modSettings;

		if (PHP_SAPI === 'cli' || empty($modSettings['databaseSession_enable']) || session_status() !== PHP_SESSION_ACTIVE)
			return;

		$sessionName = session_name();
		$sessionId = session_id();

		if ($sessionName === '' || $sessionId === '')
			return;

		$hadSessionCookie = isset($_COOKIE[$sessionName]) || self::rawCookieHeaderContains($sessionName);

		if (!self::isValidSessionId($sessionId))
		{
			// session_regenerate_id() needs strict mode relaxed when the active
			// incoming ID is invalid and not present in the database.
			$strictMode = ini_get('session.use_strict_mode');
			@ini_set('session.use_strict_mode', '0');
			$regenerated = @session_regenerate_id(true);
			@ini_set('session.use_strict_mode', $strictMode === false ? '1' : (string) $strictMode);

			if (!$regenerated || session_id() === '' || !self::isValidSessionId(session_id()))
				return;

			$sessionId = session_id();
		}

		if (isset($_COOKIE[$sessionName]) && $_COOKIE[$sessionName] === $sessionId)
			return;

		$_COOKIE[$sessionName] = $sessionId;

		if ($hadSessionCookie)
			self::sendActiveSessionCookie($sessionName, $sessionId);
	}

	private static function isValidSessionId(string $sessionId): bool
	{
		return preg_match('~^[A-Za-z0-9,-]{16,64}$~', $sessionId) === 1;
	}

	private static function sendActiveSessionCookie(string $sessionName, string $sessionId): void
	{
		if (headers_sent())
			return;

		@setcookie($sessionName, $sessionId, self::sessionCookieOptions(0));
	}

	private static function sessionCookieOptions(int $expires): array
	{
		$params = session_get_cookie_params();
		$options = array(
			'expires' => $expires,
			'path' => !empty($params['path']) ? $params['path'] : '/',
			'secure' => !empty($params['secure']) || self::isHttps(),
			'httponly' => true,
			'samesite' => 'Lax',
		);

		if (!empty($params['domain']))
			$options['domain'] = $params['domain'];

		return $options;
	}

	private static function rawCookieHeaderContains(string $cookieName): bool
	{
		if (empty($_SERVER['HTTP_COOKIE']))
			return false;

		foreach (explode(';', (string) $_SERVER['HTTP_COOKIE']) as $cookie)
		{
			$parts = explode('=', trim($cookie), 2);
			if (count($parts) === 2 && urldecode($parts[0]) === $cookieName)
				return true;
		}

		return false;
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

	private static function sendSensitiveFormCacheHeaders(): void
	{
		if (headers_sent() || !self::isSensitiveFormAction())
			return;

		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
		header('Pragma: no-cache', true);
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
	}

	private static function isSensitiveFormAction(): bool
	{
		$params = array();
		if (!empty($_SERVER['QUERY_STRING']))
			parse_str(str_replace(';', '&', (string) $_SERVER['QUERY_STRING']), $params);

		$action = isset($params['action']) ? (string) $params['action'] : (isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '');

		return in_array($action, array('login', 'login2', 'reminder', 'signup', 'signup2'), true);
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
