<?php

/**
 * XML sitemap generator for IronNoob's SMF forum.
 *
 * Generates /sitemap.xml from guest-visible forum URLs only:
 * - forum home
 * - public boards
 * - approved topics in public boards
 *
 * The sitemap follows the Sitemap Protocol: absolute URLs, UTF-8 XML,
 * <lastmod> from real forum content timestamps, and automatic sitemap-index
 * splitting if the forum ever grows beyond one file's URL limit.
 */

if (!defined('SMF'))
	die('No direct access...');

class IronNoobSitemap
{
	private const TASK_NAME = 'ironnoob_sitemap';
	private const TASK_CALLABLE = '$sourcedir/IronNoobSitemap.php|IronNoobSitemapScheduled';
	private const DEFAULT_INTERVAL_HOURS = 6;
	private const DEFAULT_MAX_URLS = 50000;
	private const PART_PATTERN = 'sitemap-forum-*.xml';
	private const PART_BASENAME = 'sitemap-forum-%d.xml';

	public static function install(): void
	{
		global $modSettings;

		$defaults = array();
		foreach (self::defaultSettings() as $setting => $default)
		{
			if (!isset($modSettings[$setting]))
				$defaults[$setting] = $default;
		}

		if (!empty($defaults))
			updateSettings($defaults);

		add_integration_function('integrate_general_mod_settings', 'IronNoobSitemap::settings', true, '$sourcedir/IronNoobSitemap.php');
		add_integration_function('integrate_save_general_mod_settings', 'IronNoobSitemap::saveSettings', true, '$sourcedir/IronNoobSitemap.php');

		self::installTask();
		self::ensureRobotsEntry();
	}

	public static function uninstall(): void
	{
		remove_integration_function('integrate_general_mod_settings', 'IronNoobSitemap::settings', true, '$sourcedir/IronNoobSitemap.php');
		remove_integration_function('integrate_save_general_mod_settings', 'IronNoobSitemap::saveSettings', true, '$sourcedir/IronNoobSitemap.php');
		self::disableTask();
	}

	public static function settings(array &$config_vars): void
	{
		self::loadText();

		$config_vars[] = '';
		$config_vars[] = array('title', 'ironnoob_sitemap_title');
		$config_vars[] = array('desc', 'ironnoob_sitemap_desc');
		$config_vars[] = array('check', 'ironnoob_sitemap_enabled');
		$config_vars[] = array('check', 'ironnoob_sitemap_include_boards');
		$config_vars[] = array('check', 'ironnoob_sitemap_include_topics');
		$config_vars[] = array('int', 'ironnoob_sitemap_interval_hours', 'min' => 1, 'max' => 168);
		$config_vars[] = array('int', 'ironnoob_sitemap_max_urls', 'min' => 1000, 'max' => self::DEFAULT_MAX_URLS);
	}

	public static function saveSettings(array &$save_vars): void
	{
		$_POST['ironnoob_sitemap_interval_hours'] = isset($_POST['ironnoob_sitemap_interval_hours'])
			? min(168, max(1, (int) $_POST['ironnoob_sitemap_interval_hours']))
			: self::DEFAULT_INTERVAL_HOURS;

		$_POST['ironnoob_sitemap_max_urls'] = isset($_POST['ironnoob_sitemap_max_urls'])
			? min(self::DEFAULT_MAX_URLS, max(1000, (int) $_POST['ironnoob_sitemap_max_urls']))
			: self::DEFAULT_MAX_URLS;

		$enabled = !empty($_POST['ironnoob_sitemap_enabled']);
		self::installTask($_POST['ironnoob_sitemap_interval_hours'], !$enabled);
	}

	public static function scheduledGenerate(): bool
	{
		global $modSettings;

		if (isset($modSettings['ironnoob_sitemap_enabled']) && empty($modSettings['ironnoob_sitemap_enabled']))
			return true;

		return self::generate();
	}

	public static function generate(): bool
	{
		global $boarddir;

		$urls = self::collectUrls();
		$maxUrls = self::maxUrlsPerFile();

		try
		{
			self::clearOldParts();

			if (count($urls) <= $maxUrls)
				self::writeUrlset($boarddir . '/sitemap.xml', $urls);
			else
				self::writeSitemapIndex($urls, $maxUrls);

			updateSettings(array(
				'ironnoob_sitemap_last_generated' => (string) time(),
				'ironnoob_sitemap_last_url_count' => (string) count($urls),
			));

			return true;
		}
		catch (Exception $e)
		{
			log_error('IronNoob sitemap generation failed: ' . $e->getMessage(), 'critical');
			return false;
		}
	}

	private static function defaultSettings(): array
	{
		return array(
			'ironnoob_sitemap_enabled' => '1',
			'ironnoob_sitemap_include_boards' => '1',
			'ironnoob_sitemap_include_topics' => '1',
			'ironnoob_sitemap_interval_hours' => (string) self::DEFAULT_INTERVAL_HOURS,
			'ironnoob_sitemap_max_urls' => (string) self::DEFAULT_MAX_URLS,
			'ironnoob_sitemap_last_generated' => '0',
			'ironnoob_sitemap_last_url_count' => '0',
		);
	}

	private static function collectUrls(): array
	{
		global $modSettings;

		$urls = array();

		$urls[] = array(
			'loc' => self::boardUrl() . '/',
			'lastmod' => self::latestPublicPostTime(),
		);

		if (!isset($modSettings['ironnoob_sitemap_include_boards']) || !empty($modSettings['ironnoob_sitemap_include_boards']))
			$urls = array_merge($urls, self::boardUrls());

		if (!isset($modSettings['ironnoob_sitemap_include_topics']) || !empty($modSettings['ironnoob_sitemap_include_topics']))
			$urls = array_merge($urls, self::topicUrls());

		return $urls;
	}

	private static function boardUrls(): array
	{
		global $smcFunc, $scripturl;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, COALESCE(m.poster_time, 0) AS last_time
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_msg_updated AND m.approved = {int:is_approved})
			WHERE ' . self::publicBoardWhere('b') . '
			ORDER BY b.board_order',
			self::queryParams()
		);

		$urls = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$urls[] = array(
				'loc' => $scripturl . '?board=' . (int) $row['id_board'] . '.0',
				'lastmod' => (int) $row['last_time'],
			);
		}
		$smcFunc['db_free_result']($request);

		return $urls;
	}

	private static function topicUrls(): array
	{
		global $smcFunc, $scripturl;

		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, COALESCE(last_msg.modified_time, 0) AS modified_time, last_msg.poster_time AS poster_time
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS last_msg ON (last_msg.id_msg = t.id_last_msg AND last_msg.approved = {int:is_approved})
			WHERE t.approved = {int:is_approved}
				AND t.id_redirect_topic = {int:no_redirect_topic}
				AND ' . self::publicBoardWhere('b') . '
			ORDER BY last_msg.poster_time DESC, t.id_topic DESC',
			self::queryParams(array(
				'no_redirect_topic' => 0,
			))
		);

		$urls = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$lastmod = max((int) $row['modified_time'], (int) $row['poster_time']);
			$urls[] = array(
				'loc' => $scripturl . '?topic=' . (int) $row['id_topic'] . '.0',
				'lastmod' => $lastmod,
			);
		}
		$smcFunc['db_free_result']($request);

		return $urls;
	}

	private static function latestPublicPostTime(): int
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT MAX(m.poster_time)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}
				AND ' . self::publicBoardWhere('b'),
			self::queryParams()
		);
		list ($latest) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $latest;
	}

	private static function publicBoardWhere(string $alias): string
	{
		return 'FIND_IN_SET({string:guest_group}, ' . $alias . '.member_groups) != 0
			AND (' . $alias . '.deny_member_groups = {string:empty_string} OR FIND_IN_SET({string:guest_group}, ' . $alias . '.deny_member_groups) = 0)
			AND ' . $alias . '.redirect = {string:empty_string}';
	}

	private static function queryParams(array $extra = array()): array
	{
		return array_merge(array(
			'guest_group' => '-1',
			'empty_string' => '',
			'is_approved' => 1,
		), $extra);
	}

	private static function writeSitemapIndex(array $urls, int $maxUrls): void
	{
		global $boarddir;

		$parts = array();
		$chunks = array_chunk($urls, $maxUrls);

		foreach ($chunks as $index => $chunk)
		{
			$filename = sprintf(self::PART_BASENAME, $index + 1);
			$path = $boarddir . '/' . $filename;
			self::writeUrlset($path, $chunk);
			$parts[] = array(
				'loc' => self::boardUrl() . '/' . $filename,
				'lastmod' => filemtime($path) ?: time(),
			);
		}

		self::writeXmlFile($boarddir . '/sitemap.xml', self::sitemapIndexXml($parts));
	}

	private static function writeUrlset(string $path, array $urls): void
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($urls as $url)
		{
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . self::xml($url['loc']) . "</loc>\n";
			if (!empty($url['lastmod']))
				$xml .= "\t\t<lastmod>" . gmdate('c', (int) $url['lastmod']) . "</lastmod>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>' . "\n";

		self::writeXmlFile($path, $xml);
	}

	private static function sitemapIndexXml(array $parts): string
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($parts as $part)
		{
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . self::xml($part['loc']) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . gmdate('c', (int) $part['lastmod']) . "</lastmod>\n";
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>' . "\n";

		return $xml;
	}

	private static function writeXmlFile(string $path, string $xml): void
	{
		$tmp = $path . '.tmp.' . getmypid();

		if (file_put_contents($tmp, $xml, LOCK_EX) === false)
			throw new Exception('Could not write temporary sitemap file: ' . basename($path));

		chmod($tmp, 0644);

		if (!rename($tmp, $path))
		{
			@unlink($tmp);
			throw new Exception('Could not move sitemap file into place: ' . basename($path));
		}
	}

	private static function clearOldParts(): void
	{
		global $boarddir;

		foreach (glob($boarddir . '/' . self::PART_PATTERN) ?: array() as $file)
		{
			if (is_file($file))
				@unlink($file);
		}
	}

	private static function installTask(?int $intervalHours = null, ?bool $disabled = null): void
	{
		global $smcFunc, $modSettings;

		$intervalHours = $intervalHours ?: self::intervalHours();
		$disabled = $disabled === null ? empty($modSettings['ironnoob_sitemap_enabled']) : $disabled;
		$nextTime = time() + 300;

		$request = $smcFunc['db_query']('', '
			SELECT id_task
			FROM {db_prefix}scheduled_tasks
			WHERE task = {string:task}
			LIMIT 1',
			array('task' => self::TASK_NAME)
		);
		list ($idTask) = $smcFunc['db_num_rows']($request) ? $smcFunc['db_fetch_row']($request) : array(0);
		$smcFunc['db_free_result']($request);

		if (!empty($idTask))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}scheduled_tasks
				SET next_time = {int:next_time}, time_offset = {int:time_offset}, time_regularity = {int:time_regularity}, time_unit = {string:time_unit}, disabled = {int:disabled}, callable = {string:callable}
				WHERE id_task = {int:id_task}',
				array(
					'next_time' => $nextTime,
					'time_offset' => 0,
					'time_regularity' => $intervalHours,
					'time_unit' => 'h',
					'disabled' => $disabled ? 1 : 0,
					'callable' => self::TASK_CALLABLE,
					'id_task' => (int) $idTask,
				)
			);
		}
		else
		{
			$smcFunc['db_query']('', '
				INSERT INTO {db_prefix}scheduled_tasks
					(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
				VALUES
					({int:next_time}, {int:time_offset}, {int:time_regularity}, {string:time_unit}, {int:disabled}, {string:task}, {string:callable})',
				array(
					'next_time' => $nextTime,
					'time_offset' => 0,
					'time_regularity' => $intervalHours,
					'time_unit' => 'h',
					'disabled' => $disabled ? 1 : 0,
					'task' => self::TASK_NAME,
					'callable' => self::TASK_CALLABLE,
				)
			);
		}

		self::updateNextTaskTime();
	}

	private static function disableTask(): void
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}scheduled_tasks
			SET disabled = {int:disabled}
			WHERE task = {string:task}',
			array(
				'disabled' => 1,
				'task' => self::TASK_NAME,
			)
		);

		self::updateNextTaskTime();
	}

	private static function updateNextTaskTime(): void
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT MIN(next_time)
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}',
			array('not_disabled' => 0)
		);
		list ($nextTime) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		updateSettings(array('next_task_time' => (string) ((int) $nextTime ?: time() + 86400)));
	}

	private static function ensureRobotsEntry(): void
	{
		global $boarddir;

		$robotsPath = $boarddir . '/robots.txt';
		$sitemapLine = 'Sitemap: ' . self::boardUrl() . '/sitemap.xml';
		$current = is_file($robotsPath) ? (string) file_get_contents($robotsPath) : '';

		if (strpos($current, $sitemapLine) !== false)
			return;

		$current = rtrim($current);
		$newContent = ($current === '' ? '' : $current . "\n") . $sitemapLine . "\n";
		file_put_contents($robotsPath, $newContent, LOCK_EX);
		chmod($robotsPath, 0644);
	}

	private static function intervalHours(): int
	{
		global $modSettings;

		return min(168, max(1, (int) ($modSettings['ironnoob_sitemap_interval_hours'] ?? self::DEFAULT_INTERVAL_HOURS)));
	}

	private static function maxUrlsPerFile(): int
	{
		global $modSettings;

		return min(self::DEFAULT_MAX_URLS, max(1000, (int) ($modSettings['ironnoob_sitemap_max_urls'] ?? self::DEFAULT_MAX_URLS)));
	}

	private static function boardUrl(): string
	{
		global $boardurl;

		return rtrim($boardurl, '/');
	}

	private static function xml(string $value): string
	{
		return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
	}

	private static function loadText(): void
	{
		global $txt;

		$txt['ironnoob_sitemap_title'] = 'XML sitemap';
		$txt['ironnoob_sitemap_desc'] = 'Automatically writes /sitemap.xml for guest-visible public boards and approved topics. The file is regenerated by SMF scheduled tasks.';
		$txt['ironnoob_sitemap_enabled'] = 'Enable automatic sitemap generation';
		$txt['ironnoob_sitemap_include_boards'] = 'Include public board URLs';
		$txt['ironnoob_sitemap_include_topics'] = 'Include approved public topic URLs';
		$txt['ironnoob_sitemap_interval_hours'] = 'Regeneration interval, in hours';
		$txt['ironnoob_sitemap_max_urls'] = 'Maximum URLs per sitemap file';
	}
}

function IronNoobSitemapScheduled(): bool
{
	return IronNoobSitemap::scheduledGenerate();
}
