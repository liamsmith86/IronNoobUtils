<?php

/**
 * Cloudflare Turnstile integration for IronNoob's SMF forum.
 *
 * Protects guest login and registration form submissions using a Turnstile
 * widget rendered client-side and verified server-side against Cloudflare's
 * Siteverify endpoint.
 */

if (!defined('SMF'))
	die('No direct access...');

class IronNoobTurnstile
{
	private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	private const SETTINGS = array(
		'ironnoob_turnstile_enabled' => '0',
		'ironnoob_turnstile_site_key' => '',
		'ironnoob_turnstile_secret_key' => '',
		'ironnoob_turnstile_on_login' => '1',
		'ironnoob_turnstile_on_register' => '1',
		'ironnoob_turnstile_theme' => 'auto',
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

		self::addHook('integrate_load_theme', 'IronNoobTurnstile::loadTheme');
		self::addHook('integrate_buffer', 'IronNoobTurnstile::buffer');
		self::addHook('integrate_validate_login', 'IronNoobTurnstile::validateLogin');
		self::addHook('integrate_actions', 'IronNoobTurnstile::actions');
		self::addHook('integrate_general_mod_settings', 'IronNoobTurnstile::settings');
		self::addHook('integrate_save_general_mod_settings', 'IronNoobTurnstile::saveSettings');
	}

	/**
	 * Remove persistent hooks. Settings are intentionally preserved.
	 */
	public static function uninstall(): void
	{
		self::removeHook('integrate_load_theme', 'IronNoobTurnstile::loadTheme');
		self::removeHook('integrate_buffer', 'IronNoobTurnstile::buffer');
		self::removeHook('integrate_validate_login', 'IronNoobTurnstile::validateLogin');
		self::removeHook('integrate_actions', 'IronNoobTurnstile::actions');
		self::removeHook('integrate_general_mod_settings', 'IronNoobTurnstile::settings');
		self::removeHook('integrate_save_general_mod_settings', 'IronNoobTurnstile::saveSettings');
	}

	/**
	 * Add language text for the admin settings page.
	 */
	public static function loadTheme(): void
	{
		self::loadText();
	}

	/**
	 * Add Turnstile settings to Admin > Configuration > Features and Options > General Mod Settings.
	 */
	public static function settings(array &$config_vars): void
	{
		self::loadText();

		$config_vars[] = '';
		$config_vars[] = array('title', 'ironnoob_turnstile_title');
		$config_vars[] = array('desc', 'ironnoob_turnstile_desc');
		$config_vars[] = array('check', 'ironnoob_turnstile_enabled');
		$config_vars[] = array('text', 'ironnoob_turnstile_site_key', 60);
		$config_vars[] = array('password', 'ironnoob_turnstile_secret_key', 60);
		$config_vars[] = array('check', 'ironnoob_turnstile_on_login');
		$config_vars[] = array('check', 'ironnoob_turnstile_on_register');
		$config_vars[] = array('select', 'ironnoob_turnstile_theme', array(
			'auto' => 'Auto',
			'light' => 'Light',
			'dark' => 'Dark',
		));
	}

	/**
	 * Trim/sanitize Turnstile settings before SMF saves them.
	 */
	public static function saveSettings(array &$save_vars): void
	{
		foreach (array('ironnoob_turnstile_site_key') as $key)
		{
			if (isset($_POST[$key]))
				$_POST[$key] = trim((string) $_POST[$key]);
		}

		if (isset($_POST['ironnoob_turnstile_secret_key']) && is_array($_POST['ironnoob_turnstile_secret_key']))
		{
			foreach ($_POST['ironnoob_turnstile_secret_key'] as $index => $value)
				$_POST['ironnoob_turnstile_secret_key'][$index] = trim((string) $value);
		}

		if (isset($_POST['ironnoob_turnstile_theme']) && !in_array($_POST['ironnoob_turnstile_theme'], array('auto', 'light', 'dark'), true))
			$_POST['ironnoob_turnstile_theme'] = 'auto';
	}

	/**
	 * Replace registration submit with a wrapper so we can verify Turnstile before SMF creates the account.
	 */
	public static function actions(array &$actionArray): void
	{
		if (self::shouldProtect('register'))
			$actionArray['signup2'] = array('IronNoobTurnstile.php', 'IronNoobTurnstile::register2');
	}

	/**
	 * Validate Turnstile on login attempts. SMF treats 'retry' as a failed login attempt.
	 */
	public static function validateLogin(string $username, ?string $password, int $cookieTime): ?string
	{
		global $txt;

		if (!self::shouldProtect('login'))
			return null;

		$result = self::verify('login');
		if ($result['success'])
			return null;

		self::loadText();
		$txt['incorrect_password'] = $txt['ironnoob_turnstile_login_failed'];

		return 'retry';
	}

	/**
	 * Wrapped Register2 action.
	 */
	public static function register2(): void
	{
		global $sourcedir, $txt;

		require_once($sourcedir . '/Register.php');

		if (!self::shouldProtect('register'))
		{
			Register2();
			return;
		}

		$result = self::verify('register');
		if (!$result['success'])
		{
			self::loadText();
			$_REQUEST['step'] = 2;

			if (isset($_SESSION['register']) && is_array($_SESSION['register']))
				$_SESSION['register']['limit'] = 5;

			Register(array($txt['ironnoob_turnstile_register_failed']));
			return;
		}

		Register2();
	}

	/**
	 * Inject a small client script that renders Turnstile widgets on protected guest forms.
	 */
	public static function buffer(string $buffer): string
	{
		global $user_info;

		if (!self::shouldLoadClient() || (!empty($user_info['id'])) || stripos($buffer, '</body>') === false)
			return $buffer;

		$config = array(
			'siteKey' => self::setting('ironnoob_turnstile_site_key'),
			'login' => self::shouldProtect('login'),
			'register' => self::shouldProtect('register'),
			'theme' => self::theme(),
		);

		$script = "\n" . '<script id="ironnoob-turnstile-loader">' . "\n" . self::clientScript($config) . "\n" . '</script>' . "\n";

		return preg_replace('~</body>~i', $script . '</body>', $buffer, 1) ?? $buffer;
	}

	private static function addHook(string $hook, string $function): void
	{
		add_integration_function($hook, $function, true, '$sourcedir/IronNoobTurnstile.php');
	}

	private static function removeHook(string $hook, string $function): void
	{
		remove_integration_function($hook, $function, true, '$sourcedir/IronNoobTurnstile.php');
	}

	private static function shouldLoadClient(): bool
	{
		if (isset($_REQUEST['xml']))
			return false;

		return self::isConfigured() && (self::setting('ironnoob_turnstile_on_login') || self::setting('ironnoob_turnstile_on_register'));
	}

	private static function shouldProtect(string $target): bool
	{
		if (!self::isConfigured())
			return false;

		if ($target === 'login')
			return self::setting('ironnoob_turnstile_on_login') === '1';

		if ($target === 'register')
			return self::setting('ironnoob_turnstile_on_register') === '1';

		return false;
	}

	private static function isConfigured(): bool
	{
		return self::setting('ironnoob_turnstile_enabled') === '1'
			&& self::setting('ironnoob_turnstile_site_key') !== ''
			&& self::setting('ironnoob_turnstile_secret_key') !== '';
	}

	private static function setting(string $key): string
	{
		global $modSettings;

		return isset($modSettings[$key]) ? (string) $modSettings[$key] : (self::SETTINGS[$key] ?? '');
	}

	private static function theme(): string
	{
		$theme = self::setting('ironnoob_turnstile_theme');

		return in_array($theme, array('auto', 'light', 'dark'), true) ? $theme : 'auto';
	}

	private static function verify(string $expectedAction): array
	{
		$token = isset($_POST['cf-turnstile-response']) ? trim((string) $_POST['cf-turnstile-response']) : '';
		if ($token === '')
			return self::verificationResult(false, 'missing-input-response');

		$response = self::postVerify(array(
			'secret' => self::setting('ironnoob_turnstile_secret_key'),
			'response' => $token,
			'remoteip' => self::clientIp(),
		));

		if ($response === null)
			return self::verificationResult(false, 'siteverify-unavailable');

		$data = json_decode($response, true);
		if (!is_array($data))
			return self::verificationResult(false, 'siteverify-invalid-json');

		if (empty($data['success']))
			return self::verificationResult(false, !empty($data['error-codes']) && is_array($data['error-codes']) ? implode(',', $data['error-codes']) : 'siteverify-failed');

		if (!empty($data['action']) && !hash_equals($expectedAction, (string) $data['action']))
			return self::verificationResult(false, 'action-mismatch');

		$isTestingKey = !empty($data['metadata']) && is_array($data['metadata']) && !empty($data['metadata']['result_with_testing_key']);

		if (!$isTestingKey && !empty($data['hostname']) && !self::hostnameAllowed((string) $data['hostname']))
			return self::verificationResult(false, 'hostname-mismatch');

		return self::verificationResult(true, 'ok');
	}

	private static function verificationResult(bool $success, string $reason): array
	{
		return array(
			'success' => $success,
			'reason' => $reason,
		);
	}

	private static function postVerify(array $fields): ?string
	{
		$payload = http_build_query($fields, '', '&');

		if (function_exists('curl_init'))
		{
			$ch = curl_init(self::VERIFY_URL);
			curl_setopt_array($ch, array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $payload,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 8,
				CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			));

			$response = curl_exec($ch);
			$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			return $response !== false && $status >= 200 && $status < 300 ? $response : null;
		}

		$context = stream_context_create(array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
				'content' => $payload,
				'timeout' => 8,
			),
		));

		$response = @file_get_contents(self::VERIFY_URL, false, $context);

		return $response !== false ? $response : null;
	}

	private static function clientIp(): string
	{
		$candidates = array(
			$_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
			$_SERVER['REMOTE_ADDR'] ?? '',
		);

		foreach ($candidates as $candidate)
		{
			$candidate = trim((string) $candidate);
			if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP))
				return $candidate;
		}

		return '';
	}

	private static function hostnameAllowed(string $hostname): bool
	{
		$hostname = strtolower(preg_replace('/:\\d+$/', '', trim($hostname)));
		$currentHost = strtolower(preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
		$boardHost = self::boardHost();

		return $hostname !== '' && in_array($hostname, array_filter(array($currentHost, $boardHost)), true);
	}

	private static function boardHost(): string
	{
		global $boardurl;

		$host = parse_url($boardurl, PHP_URL_HOST);

		return is_string($host) ? strtolower($host) : '';
	}

	private static function clientScript(array $config): string
	{
		$json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		return <<<JS
(function () {
	'use strict';

	var cfg = {$json};
	var apiRequested = false;
	var observerStarted = false;

	function normalizedAction(form) {
		var action = (form.getAttribute('action') || '').replace(/&amp;/g, '&');

		if (cfg.login && /(?:[?;&]|^)action=login2(?:[;&]|$)/.test(action)) {
			return 'login';
		}

		if (cfg.register && /(?:[?;&]|^)action=signup2(?:[;&]|$)/.test(action)) {
			return 'register';
		}

		return '';
	}

	function candidates() {
		var forms = document.querySelectorAll('form[action]');
		var matches = [];

		for (var i = 0; i < forms.length; i++) {
			if (forms[i].getAttribute('data-ironnoob-turnstile') === 'rendered') {
				continue;
			}

			var action = normalizedAction(forms[i]);
			if (action) {
				matches.push({ form: forms[i], action: action });
			}
		}

		return matches;
	}

	function requestApi() {
		if (apiRequested) {
			return;
		}

		apiRequested = true;
		window.ironnoobTurnstileReady = processForms;

		var script = document.createElement('script');
		script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=ironnoobTurnstileReady';
		script.async = true;
		script.defer = true;
		document.head.appendChild(script);
	}

	function render(item) {
		if (!window.turnstile || typeof window.turnstile.render !== 'function') {
			return;
		}

		var form = item.form;
		var holder = document.createElement('div');
		holder.className = 'ironnoob-turnstile-wrap';
		holder.style.margin = '0.75em 0';
		holder.setAttribute('aria-live', 'polite');

		var submit = form.querySelector('input[type="submit"], button[type="submit"], button:not([type])');
		if (submit && submit.parentNode) {
			submit.parentNode.insertBefore(holder, submit);
		}
		else {
			form.appendChild(holder);
		}

		form.setAttribute('data-ironnoob-turnstile', 'rendered');

		window.turnstile.render(holder, {
			sitekey: cfg.siteKey,
			theme: cfg.theme || 'auto',
			action: item.action,
			// Keep the header quick-login unobtrusive; if Cloudflare needs
			// interaction, it can still surface a challenge before submit.
			appearance: form.id === 'guest_form' ? 'interaction-only' : 'always',
			size: 'normal'
		});
	}

	function processForms() {
		if (!cfg.siteKey) {
			return;
		}

		var matches = candidates();
		if (!matches.length) {
			return;
		}

		if (!window.turnstile || typeof window.turnstile.render !== 'function') {
			requestApi();
			return;
		}

		for (var i = 0; i < matches.length; i++) {
			render(matches[i]);
		}
	}

	function startObserver() {
		if (observerStarted || !window.MutationObserver || !document.documentElement) {
			return;
		}

		observerStarted = true;
		new MutationObserver(processForms).observe(document.documentElement, {
			childList: true,
			subtree: true
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			processForms();
			startObserver();
		});
	}
	else {
		processForms();
		startObserver();
	}
})();
JS;
	}

	private static function loadText(): void
	{
		global $txt;

		$txt['ironnoob_turnstile_title'] = 'Cloudflare Turnstile';
		$txt['ironnoob_turnstile_desc'] = 'Adds Cloudflare Turnstile to guest login and registration forms. Create a Turnstile widget for ironnoob.net in Cloudflare, paste the site and secret keys here, then enable protection.';
		$txt['ironnoob_turnstile_enabled'] = 'Enable Cloudflare Turnstile protection';
		$txt['ironnoob_turnstile_site_key'] = 'Turnstile site key';
		$txt['ironnoob_turnstile_secret_key'] = 'Turnstile secret key';
		$txt['ironnoob_turnstile_on_login'] = 'Protect guest login form submissions';
		$txt['ironnoob_turnstile_on_register'] = 'Protect registration form submissions';
		$txt['ironnoob_turnstile_theme'] = 'Turnstile theme';
		$txt['ironnoob_turnstile_login_failed'] = 'Please complete the anti-spam verification and try logging in again.';
		$txt['ironnoob_turnstile_register_failed'] = 'Please complete the anti-spam verification and try registering again.';
	}
}
