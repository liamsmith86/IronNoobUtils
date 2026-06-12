<?php

/**
 * Hourly AI forum bot runner for IronNoob's SMF forum.
 *
 * The public behavior is intentionally simple: each run picks one persona, then
 * either drafts a new topic or replies to one of the recently active public
 * topics. Posting uses SMF's createPost helper so counts/search metadata stay
 * consistent. LLM credentials live outside the webroot.
 */

if (!defined('SMF'))
    die('No direct access...');

class IronNoobAIBots
{
    private const SETTINGS = [
        'ironnoob_ai_bots_enabled' => '1',
        'ironnoob_ai_bots_dry_run' => '1',
        'ironnoob_ai_bots_new_topic_chance' => '2',
        'ironnoob_ai_bots_recent_topics' => '20',
        'ironnoob_ai_bots_context_messages' => '20',
        'ironnoob_ai_bots_context_chars' => '40000',
        'ironnoob_ai_bots_num_ctx' => '32768',
        'ironnoob_ai_bots_new_topic_board' => '0',
        'ironnoob_ai_bots_max_body_chars' => '1800',
        'ironnoob_ai_bots_min_seconds_between_posts' => '3300',
        'ironnoob_ai_bots_budget_enabled' => '1',
        'ironnoob_ai_post_chance_min' => '30',
        'ironnoob_ai_post_chance_max' => '70',
        'ironnoob_ai_bots_max_posts_per_day' => '8',
        'ironnoob_ai_bots_quiet_start_hour' => '0',
        'ironnoob_ai_bots_quiet_end_hour' => '8',
        'ironnoob_ai_bots_dedupe_enabled' => '1',
        'ironnoob_ai_bots_dedupe_memory_size' => '200',
        'ironnoob_ai_bots_dedupe_days' => '30',
        'ironnoob_ai_bots_similarity_threshold' => '58',
        'ironnoob_ai_bots_dedupe_retry_attempts' => '3',
        'ironnoob_ai_bots_last_run' => '0',
        'ironnoob_ai_bots_last_post' => '0',
    ];

    private const CONFIG_FILE = '/home/liamsmit/.config/ironnoob-ai-bots/config.php';
    private const PERSONAS_FILE = '/home/liamsmit/.config/ironnoob-ai-bots/personas.json';
    private const STATE_FILE = '/home/liamsmit/.local/state/ironnoob-ai-bots/state.json';
    private const LOCK_FILE = '/home/liamsmit/.cache/ironnoob-ai-bots.lock';
    private const LOG_FILE = '/home/liamsmit/logs/ironnoob-ai-bots.log';
    private const USERNAME_PREFIX = 'AI ';

    public static function install(): void
    {
        global $modSettings;

        $defaults = [];
        foreach (self::SETTINGS as $setting => $default) {
            if (!isset($modSettings[$setting])) {
                $defaults[$setting] = $default;
            }
        }

        if (!empty($defaults)) {
            updateSettings($defaults);
        }

        self::addHook('integrate_load_theme', 'IronNoobAIBots::loadTheme');
        self::addHook('integrate_general_mod_settings', 'IronNoobAIBots::settings');
        self::addHook('integrate_save_general_mod_settings', 'IronNoobAIBots::saveSettings');
    }

    public static function uninstall(): void
    {
        self::removeHook('integrate_load_theme', 'IronNoobAIBots::loadTheme');
        self::removeHook('integrate_general_mod_settings', 'IronNoobAIBots::settings');
        self::removeHook('integrate_save_general_mod_settings', 'IronNoobAIBots::saveSettings');
    }

    public static function loadTheme(): void
    {
        self::loadText();
    }

    public static function settings(array &$config_vars): void
    {
        self::loadText();

        $config_vars[] = '';
        $config_vars[] = ['title', 'ironnoob_ai_bots_title'];
        $config_vars[] = ['desc', 'ironnoob_ai_bots_desc'];
        $config_vars[] = ['check', 'ironnoob_ai_bots_enabled'];
        $config_vars[] = ['check', 'ironnoob_ai_bots_dry_run'];
        $config_vars[] = ['int', 'ironnoob_ai_bots_new_topic_chance', 'min' => 0, 'max' => 100];
        $config_vars[] = ['int', 'ironnoob_ai_bots_recent_topics', 'min' => 5, 'max' => 50];
        $config_vars[] = ['int', 'ironnoob_ai_bots_context_messages', 'min' => 3, 'max' => 50];
        $config_vars[] = ['int', 'ironnoob_ai_bots_context_chars', 'min' => 4000, 'max' => 200000];
        $config_vars[] = ['int', 'ironnoob_ai_bots_num_ctx', 'min' => 4096, 'max' => 262144];
        $config_vars[] = ['int', 'ironnoob_ai_bots_new_topic_board', 'min' => 0, 'max' => 999999];
        $config_vars[] = ['int', 'ironnoob_ai_bots_max_body_chars', 'min' => 200, 'max' => 5000];
        $config_vars[] = ['int', 'ironnoob_ai_bots_min_seconds_between_posts', 'min' => 0, 'max' => 86400];
        $config_vars[] = ['check', 'ironnoob_ai_bots_budget_enabled'];
        $config_vars[] = ['int', 'ironnoob_ai_post_chance_min', 'min' => 0, 'max' => 100];
        $config_vars[] = ['int', 'ironnoob_ai_post_chance_max', 'min' => 0, 'max' => 100];
        $config_vars[] = ['int', 'ironnoob_ai_bots_max_posts_per_day', 'min' => 0, 'max' => 100];
        $config_vars[] = ['int', 'ironnoob_ai_bots_quiet_start_hour', 'min' => 0, 'max' => 23];
        $config_vars[] = ['int', 'ironnoob_ai_bots_quiet_end_hour', 'min' => 0, 'max' => 23];
        $config_vars[] = ['check', 'ironnoob_ai_bots_dedupe_enabled'];
        $config_vars[] = ['int', 'ironnoob_ai_bots_dedupe_memory_size', 'min' => 10, 'max' => 1000];
        $config_vars[] = ['int', 'ironnoob_ai_bots_dedupe_days', 'min' => 1, 'max' => 365];
        $config_vars[] = ['int', 'ironnoob_ai_bots_similarity_threshold', 'min' => 20, 'max' => 100];
        $config_vars[] = ['int', 'ironnoob_ai_bots_dedupe_retry_attempts', 'min' => 1, 'max' => 5];
    }

    public static function saveSettings(array &$save_vars): void
    {
        $_POST['ironnoob_ai_bots_new_topic_chance'] = self::clampPostInt('ironnoob_ai_bots_new_topic_chance', 0, 100, 2);
        $_POST['ironnoob_ai_bots_recent_topics'] = self::clampPostInt('ironnoob_ai_bots_recent_topics', 5, 50, 20);
        $_POST['ironnoob_ai_bots_context_messages'] = self::clampPostInt('ironnoob_ai_bots_context_messages', 3, 50, 20);
        $_POST['ironnoob_ai_bots_context_chars'] = self::clampPostInt('ironnoob_ai_bots_context_chars', 4000, 200000, 40000);
        $_POST['ironnoob_ai_bots_num_ctx'] = self::clampPostInt('ironnoob_ai_bots_num_ctx', 4096, 262144, 32768);
        $_POST['ironnoob_ai_bots_new_topic_board'] = self::clampPostInt('ironnoob_ai_bots_new_topic_board', 0, 999999, 0);
        $_POST['ironnoob_ai_bots_max_body_chars'] = self::clampPostInt('ironnoob_ai_bots_max_body_chars', 200, 5000, 1800);
        $_POST['ironnoob_ai_bots_min_seconds_between_posts'] = self::clampPostInt('ironnoob_ai_bots_min_seconds_between_posts', 0, 86400, 3300);
        $_POST['ironnoob_ai_post_chance_min'] = self::clampPostInt('ironnoob_ai_post_chance_min', 0, 100, 30);
        $_POST['ironnoob_ai_post_chance_max'] = self::clampPostInt('ironnoob_ai_post_chance_max', 0, 100, 70);
        if ($_POST['ironnoob_ai_post_chance_max'] < $_POST['ironnoob_ai_post_chance_min']) {
            $_POST['ironnoob_ai_post_chance_max'] = $_POST['ironnoob_ai_post_chance_min'];
        }
        $_POST['ironnoob_ai_bots_max_posts_per_day'] = self::clampPostInt('ironnoob_ai_bots_max_posts_per_day', 0, 100, 8);
        $_POST['ironnoob_ai_bots_quiet_start_hour'] = self::clampPostInt('ironnoob_ai_bots_quiet_start_hour', 0, 23, 0);
        $_POST['ironnoob_ai_bots_quiet_end_hour'] = self::clampPostInt('ironnoob_ai_bots_quiet_end_hour', 0, 23, 8);
        $_POST['ironnoob_ai_bots_dedupe_memory_size'] = self::clampPostInt('ironnoob_ai_bots_dedupe_memory_size', 10, 1000, 200);
        $_POST['ironnoob_ai_bots_dedupe_days'] = self::clampPostInt('ironnoob_ai_bots_dedupe_days', 1, 365, 30);
        $_POST['ironnoob_ai_bots_similarity_threshold'] = self::clampPostInt('ironnoob_ai_bots_similarity_threshold', 20, 100, 58);
        $_POST['ironnoob_ai_bots_dedupe_retry_attempts'] = self::clampPostInt('ironnoob_ai_bots_dedupe_retry_attempts', 1, 5, 3);
    }

    public static function runFromCli(array $argv = []): int
    {
        $opts = [
            'dry_run' => in_array('--dry-run', $argv, true),
            'live' => in_array('--live', $argv, true),
            'force' => in_array('--force', $argv, true),
            'ensure_users' => in_array('--ensure-users', $argv, true),
        ];

        try {
            self::run($opts);
            return 0;
        } catch (Throwable $e) {
            self::log('ERROR ' . $e->getMessage());
            return 1;
        }
    }

    public static function run(array $opts = []): void
    {
        global $modSettings;

        self::ensureRuntimeDirs();

        $lock = fopen(self::LOCK_FILE, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            self::log('Skipped: another run is still active.');
            return;
        }

        try {
            reloadSettings();

            if (empty($opts['force']) && empty($modSettings['ironnoob_ai_bots_enabled'])) {
                self::log('Skipped: disabled in settings.');
                return;
            }

            $dryRun = !empty($opts['live']) ? false : (!empty($opts['dry_run']) || !empty($modSettings['ironnoob_ai_bots_dry_run']));
            if (!empty($opts['ensure_users'])) {
                $ensured = self::ensureAllMembers();
                self::log('Ensured AI member accounts: ' . $ensured);
                if ($dryRun && empty($opts['live'])) {
                    return;
                }
            }

            $lastPost = (int) ($modSettings['ironnoob_ai_bots_last_post'] ?? 0);
            $minGap = (int) ($modSettings['ironnoob_ai_bots_min_seconds_between_posts'] ?? 3300);
            if (!$dryRun && empty($opts['force']) && $lastPost > 0 && time() - $lastPost < $minGap) {
                self::log('Skipped: min post gap has not elapsed.');
                return;
            }

            updateSettings(['ironnoob_ai_bots_last_run' => (string) time()]);

            $persona = self::pickPersona();

            $state = self::loadState();
            self::resetDailyBudgetIfNeeded($state);
            if (!self::shouldAttemptPost($opts, $dryRun, $state)) {
                self::saveState($state);
                return;
            }

            $newTopicChance = (int) ($modSettings['ironnoob_ai_bots_new_topic_chance'] ?? 2);
            $wantsNewTopic = random_int(1, 100) <= $newTopicChance;
            $draft = self::draftWithDedupe($persona, $wantsNewTopic, $state);
            if ($draft === null) {
                self::log('Skipped: no usable non-duplicate board/topic/context was available.');
                self::saveState($state);
                return;
            }

            if ($dryRun) {
                self::log('DRY RUN ' . json_encode([
                    'persona' => $persona['username'],
                    'action' => $draft['action'],
                    'board' => $draft['board'] ?? null,
                    'topic' => $draft['topic'] ?? null,
                    'subject' => $draft['subject'] ?? null,
                    'body' => self::truncateForLog($draft['body']),
                ], JSON_UNESCAPED_SLASHES));
                return;
            }

            $member = self::ensureMember($persona['username']);
            self::publishDraft($member, $draft);
            updateSettings(['ironnoob_ai_bots_last_post' => (string) time()]);
            self::recordPublishedDraft($state, $persona, $draft);
            self::saveState($state);
            self::log('POSTED ' . json_encode([
                'persona' => $persona['username'],
                'action' => $draft['action'],
                'board' => $draft['board'] ?? null,
                'topic' => $draft['topic'] ?? null,
                'subject' => $draft['subject'] ?? null,
            ], JSON_UNESCAPED_SLASHES));
        } finally {
            if (isset($lock) && is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private static function draftWithDedupe(array $persona, bool $wantsNewTopic, array $state): ?array
    {
        $attempts = self::dedupeEnabled() ? self::settingInt('ironnoob_ai_bots_dedupe_retry_attempts', 3, 1, 5) : 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $draft = self::draftForPreference($persona, $wantsNewTopic);
            if ($draft === null) {
                continue;
            }

            $similar = self::findSimilarOutput($draft['body'], $state);
            if ($similar === null) {
                return $draft;
            }

            self::log('Rejected similar AI draft ' . json_encode([
                'attempt' => $attempt,
                'persona' => $persona['username'],
                'score' => $similar['score'],
                'matched_persona' => $similar['persona'] ?? null,
                'matched_subject' => $similar['subject'] ?? null,
            ], JSON_UNESCAPED_SLASHES));
        }

        return null;
    }

    private static function draftForPreference(array $persona, bool $wantsNewTopic): ?array
    {
        $draft = $wantsNewTopic ? self::draftNewTopic($persona) : self::draftReply($persona);

        // Be resilient: if the preferred action cannot find usable context,
        // try the other action before skipping the run.
        if ($draft === null) {
            $draft = $wantsNewTopic ? self::draftReply($persona) : self::draftNewTopic($persona);
        }

        return $draft;
    }

    private static function draftReply(array $persona): ?array
    {
        // Pick a random public board first so bot replies are not biased toward
        // whichever boards happen to have the newest global activity.
        $board = self::pickPublicBoard(true);
        $topics = $board === null ? [] : self::recentTopics((int) $board['id_board']);

        // If the randomly selected board somehow has no usable topics anymore,
        // fall back to any recent public topic rather than failing the run.
        if (empty($topics)) {
            $topics = self::recentTopics(null);
        }
        if (empty($topics)) {
            return null;
        }

        shuffle($topics);
        foreach ($topics as $topic) {
            $messages = self::topicMessages((int) $topic['id_topic']);
            if (empty($messages)) {
                continue;
            }

            $system = self::systemPrompt($persona, 'reply');
            $user = "Reply casually to this forum thread. Output JSON only: {\"body\":\"...\"}.\n\n";
            $user .= "Thread: " . self::plain($topic['subject']) . "\n";
            $user .= "Board: " . self::plain($topic['board_name']) . "\n\n";
            $user .= "Recent posts:\n" . self::formatMessages($messages, self::contextCharBudget());

            $content = self::callLlm($system, $user);
            $parsed = self::parseJsonish($content);
            $body = self::cleanBody($parsed['body'] ?? $content);
            if ($body === '') {
                continue;
            }

            return [
                'action' => 'reply',
                'topic' => (int) $topic['id_topic'],
                'board' => (int) $topic['id_board'],
                'subject' => 'Re: ' . self::plain($topic['subject']),
                'body' => $body,
            ];
        }

        return null;
    }

    private static function draftNewTopic(array $persona): ?array
    {
        $board = self::newTopicBoard();
        if ($board === null) {
            return null;
        }

        $topics = self::recentTopics((int) $board['id_board']);
        if (empty($topics)) {
            $topics = self::recentTopics(null);
        }

        $system = self::systemPrompt($persona, 'new_topic');
        $user = "Start a new casual forum thread for the IronNoob forum. Output JSON only: {\"subject\":\"...\",\"body\":\"...\"}.\n";
        $user .= "Target board: " . self::plain($board['name']) . "\n\n";
        $user .= "Recent thread context so you do not duplicate existing topics:\n" . self::formatTopics($topics, min(20000, self::contextCharBudget()));

        $content = self::callLlm($system, $user);
        $parsed = self::parseJsonish($content);
        $subject = self::cleanSubject($parsed['subject'] ?? 'random thought');
        $body = self::cleanBody($parsed['body'] ?? $content);
        if ($body === '') {
            return null;
        }

        return [
            'action' => 'new_topic',
            'board' => (int) $board['id_board'],
            'subject' => $subject,
            'body' => $body,
        ];
    }

    private static function publishDraft(array $member, array $draft): void
    {
        global $sourcedir;

        require_once($sourcedir . '/Subs-Post.php');

        $msgOptions = [
            'subject' => $draft['subject'],
            'body' => $draft['body'],
            'icon' => 'xx',
            'smileys_enabled' => true,
            'approved' => 1,
            'send_notifications' => false,
        ];
        $topicOptions = [
            'id' => $draft['action'] === 'reply' ? (int) $draft['topic'] : 0,
            'board' => (int) $draft['board'],
            'mark_as_read' => false,
        ];
        $posterOptions = [
            'id' => (int) $member['id_member'],
            'name' => $member['member_name'],
            'email' => $member['email_address'],
            'ip' => '127.0.0.1',
            'update_post_count' => true,
        ];

        if (!createPost($msgOptions, $topicOptions, $posterOptions)) {
            throw new RuntimeException('SMF createPost failed.');
        }
    }

    private static function ensureAllMembers(): int
    {
        $usernames = [];
        foreach (self::personas() as $persona) {
            $usernames[$persona['username']] = true;
        }

        foreach (array_keys($usernames) as $username) {
            self::ensureMember($username);
        }

        return count($usernames);
    }

    private static function ensureMember(string $username): array
    {
        global $smcFunc, $sourcedir;

        $member = self::memberByName($username);
        if ($member !== null) {
            return $member;
        }

        require_once($sourcedir . '/Subs-Members.php');

        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $_SERVER['BAN_CHECK_IP'] = $_SERVER['BAN_CHECK_IP'] ?? '127.0.0.1';

        $password = bin2hex(random_bytes(24));
        $emailSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $username));
        $email = 'ai+' . trim($emailSlug, '-') . '@ironnoob.net';
        $regOptions = [
            'interface' => 'ironnoob_ai_bots',
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'password_check' => $password,
            'require' => 'nothing',
            'check_reserved_name' => true,
            'check_password_strength' => false,
            'check_email_ban' => false,
            'send_welcome_email' => false,
            'extra_register_vars' => [
                'show_online' => 0,
                'usertitle' => 'AI poster',
                'personal_text' => 'definitely not a robot',
            ],
            'theme_vars' => [],
        ];

        $errors = registerMember($regOptions, true);
        if (is_array($errors) && !empty($errors)) {
            throw new RuntimeException('Could not create AI member ' . $username . ': ' . implode('; ', $errors));
        }

        $member = self::memberByName($username);
        if ($member === null) {
            throw new RuntimeException('AI member creation did not return a usable account for ' . $username);
        }

        self::log('Created AI member ' . $username . ' (#' . $member['id_member'] . ')');
        return $member;
    }

    private static function memberByName(string $username): ?array
    {
        global $smcFunc;

        $request = $smcFunc['db_query']('', '
            SELECT id_member, member_name, real_name, email_address
            FROM {db_prefix}members
            WHERE member_name = {string:name}
            LIMIT 1',
            ['name' => $username]
        );
        $row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);

        return $row ?: null;
    }

    private static function recentTopics(?int $boardId = null): array
    {
        global $smcFunc, $modSettings;

        $limit = (int) ($modSettings['ironnoob_ai_bots_recent_topics'] ?? 20);
        $request = $smcFunc['db_query']('', '
            SELECT t.id_topic, t.id_board, b.name AS board_name, mf.subject, mf.body AS first_body, ml.poster_time AS last_time
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
                INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
            WHERE t.approved = {int:approved}
                AND t.locked = {int:unlocked}
                AND b.redirect = {string:empty_string}
                AND FIND_IN_SET({string:guest_group}, b.member_groups) != 0'
                . ($boardId === null ? '' : '
                AND t.id_board = {int:board}') . '
            ORDER BY t.id_last_msg DESC
            LIMIT {int:limit}',
            [
                'approved' => 1,
                'unlocked' => 0,
                'empty_string' => '',
                'guest_group' => '-1',
                'board' => $boardId ?? 0,
                'limit' => $limit,
            ]
        );

        $topics = [];
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $topics[] = $row;
        }
        $smcFunc['db_free_result']($request);

        return $topics;
    }

    private static function topicMessages(int $topicId): array
    {
        global $smcFunc, $modSettings;

        $limit = (int) ($modSettings['ironnoob_ai_bots_context_messages'] ?? 20);
        $request = $smcFunc['db_query']('', '
            SELECT id_msg, poster_name, subject, body, poster_time
            FROM {db_prefix}messages
            WHERE id_topic = {int:topic} AND approved = {int:approved}
            ORDER BY id_msg DESC
            LIMIT {int:limit}',
            ['topic' => $topicId, 'approved' => 1, 'limit' => $limit]
        );

        $messages = [];
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $messages[] = $row;
        }
        $smcFunc['db_free_result']($request);

        return array_reverse($messages);
    }

    private static function newTopicBoard(): ?array
    {
        global $modSettings;

        $boardId = (int) ($modSettings['ironnoob_ai_bots_new_topic_board'] ?? 0);
        if ($boardId > 0) {
            $board = self::publicBoardById($boardId);
            if ($board !== null) {
                return $board;
            }
        }

        return self::pickPublicBoard(false);
    }

    private static function pickPublicBoard(bool $requireTopics): ?array
    {
        $boards = self::publicBoards($requireTopics);
        if (empty($boards) && $requireTopics) {
            $boards = self::publicBoards(false);
        }
        if (empty($boards)) {
            return null;
        }

        return $boards[array_rand($boards)];
    }

    private static function publicBoardById(int $boardId): ?array
    {
        foreach (self::publicBoards(false) as $board) {
            if ((int) $board['id_board'] === $boardId) {
                return $board;
            }
        }

        return null;
    }

    private static function publicBoards(bool $requireTopics): array
    {
        global $smcFunc;

        $request = $smcFunc['db_query']('', '
            SELECT b.id_board, b.name, b.num_topics
            FROM {db_prefix}boards AS b
            WHERE b.redirect = {string:empty_string}
                AND FIND_IN_SET({string:guest_group}, b.member_groups) != 0'
                . ($requireTopics ? '
                AND b.num_topics > {int:no_topics}' : '') . '
            ORDER BY b.id_board',
            [
                'empty_string' => '',
                'guest_group' => '-1',
                'no_topics' => 0,
            ]
        );

        $boards = [];
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $boards[] = $row;
        }
        $smcFunc['db_free_result']($request);

        return $boards;
    }

    private static function callLlm(string $system, string $user): string
    {
        $config = self::loadConfig();
        $url = rtrim((string) $config['llm_host'], '/') . '/api/chat';
        $payload = [
            'model' => $config['llm_model'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'stream' => false,
            'options' => [
                'num_ctx' => self::numCtx(),
                'temperature' => 0.9,
                'top_p' => 0.9,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($config['llm_user'] . ':' . $config['llm_pass']),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 240,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('LLM request failed with HTTP ' . $status . ($error ? ': ' . $error : ''));
        }

        $data = json_decode($response, true);
        $content = $data['message']['content'] ?? $data['response'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('LLM returned an empty response.');
        }

        return trim($content);
    }

    private static function loadConfig(): array
    {
        if (!is_file(self::CONFIG_FILE)) {
            throw new RuntimeException('Missing AI bot config file: ' . self::CONFIG_FILE);
        }

        $config = require self::CONFIG_FILE;
        foreach (['llm_host', 'llm_user', 'llm_pass', 'llm_model'] as $key) {
            if (empty($config[$key]) || !is_string($config[$key])) {
                throw new RuntimeException('AI bot config is missing ' . $key);
            }
        }

        return $config;
    }

    private static function pickPersona(): array
    {
        $personas = self::personas();
        return $personas[array_rand($personas)];
    }

    private static function personas(): array
    {
        if (!is_file(self::PERSONAS_FILE)) {
            throw new RuntimeException('Missing AI personas JSON file: ' . self::PERSONAS_FILE);
        }

        $raw = file_get_contents(self::PERSONAS_FILE);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid AI personas JSON.');
        }

        $personas = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $username = trim((string) ($row['username'] ?? ($row['name'] ?? '')));
            if ($username === '') {
                continue;
            }
            if (strpos($username, self::USERNAME_PREFIX) !== 0) {
                $username = self::botUsername($username);
            }

            $prompt = trim((string) ($row['prompt'] ?? 'casual teen forum/group-chat tone. short, chill, a little goofy, never formal or corporate.'));
            $personas[] = [
                'username' => $username,
                'prompt' => $prompt,
            ];
        }

        if (empty($personas)) {
            throw new RuntimeException('No valid AI personas configured.');
        }

        return $personas;
    }

    private static function botUsername(string $baseName): string
    {
        return self::USERNAME_PREFIX . $baseName;
    }

    private static function systemPrompt(array $persona, string $mode): string
    {
        $task = $mode === 'new_topic' ? 'start a new forum thread' : 'reply to an existing forum thread';
        return "You are posting on IronNoob as {$persona['username']}. Personality: {$persona['prompt']} "
            . "Your job is to {$task}. Write like a real casual forum user, not an assistant. "
            . "Do not mention being AI, a model, a bot, a prompt, or having context. "
            . "Keep it natural, specific to the thread, and usually 1-5 short sentences. "
            . "No signatures, no hashtags, no markdown headings, no generic positivity."
            . self::recentOutputAvoidancePrompt();
    }

    private static function parseJsonish(string $content): array
    {
        $trimmed = trim($content);
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/', '', $trimmed);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{.*\}/s', $trimmed, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return ['body' => $content];
    }

    private static function cleanSubject(string $subject): string
    {
        $subject = self::plain($subject);
        $subject = preg_replace('/\s+/', ' ', $subject);
        $subject = trim($subject, " \t\n\r\0\x0B.:-");
        if ($subject === '') {
            $subject = 'random thought';
        }
        return mb_substr($subject, 0, 80);
    }

    private static function cleanBody(string $body): string
    {
        global $modSettings;

        $body = trim(str_replace(["\r\n", "\r"], "\n", $body));
        $body = preg_replace('/<\/?(?:script|iframe|object|embed)\b[^>]*>/i', '', $body);
        $body = preg_replace('/\[\/?html\]/i', '', $body);
        $body = preg_replace('/@([A-Za-z0-9_\-]+)/', '$1', $body);
        $body = preg_replace("/\n{4,}/", "\n\n\n", $body);
        $max = (int) ($modSettings['ironnoob_ai_bots_max_body_chars'] ?? 1800);
        if (mb_strlen($body) > $max) {
            $body = rtrim(mb_substr($body, 0, $max - 1)) . '…';
        }
        return trim($body);
    }

    private static function formatMessages(array $messages, int $budgetChars): string
    {
        if (empty($messages)) {
            return "(No posts found.)\n";
        }

        $perMessage = max(700, (int) floor($budgetChars / max(1, count($messages))));
        $lines = [];
        $used = 0;
        foreach ($messages as $message) {
            $prefix = '- ' . self::plain($message['poster_name']) . ': ';
            $remaining = max(300, $budgetChars - $used - mb_strlen($prefix));
            $limit = min($perMessage, $remaining);
            $line = $prefix . self::excerpt($message['body'], $limit);
            $lines[] = $line;
            $used += mb_strlen($line) + 1;
            if ($used >= $budgetChars) {
                break;
            }
        }
        return implode("\n", $lines);
    }

    private static function formatTopics(array $topics, int $budgetChars): string
    {
        if (empty($topics)) {
            return "(No recent threads found.)\n";
        }

        $perTopic = max(220, (int) floor($budgetChars / max(1, count($topics))));
        $lines = [];
        $used = 0;
        foreach ($topics as $topic) {
            $prefix = '- [' . self::plain($topic['board_name']) . '] ' . self::plain($topic['subject']) . ': ';
            $remaining = max(160, $budgetChars - $used - mb_strlen($prefix));
            $limit = min($perTopic, $remaining);
            $line = $prefix . self::excerpt($topic['first_body'], $limit);
            $lines[] = $line;
            $used += mb_strlen($line) + 1;
            if ($used >= $budgetChars) {
                break;
            }
        }
        return implode("\n", $lines) . "\n";
    }

    private static function contextCharBudget(): int
    {
        global $modSettings;

        return max(4000, min(200000, (int) ($modSettings['ironnoob_ai_bots_context_chars'] ?? 40000)));
    }

    private static function numCtx(): int
    {
        global $modSettings;

        return max(4096, min(262144, (int) ($modSettings['ironnoob_ai_bots_num_ctx'] ?? 32768)));
    }

    private static function shouldAttemptPost(array $opts, bool $dryRun, array &$state): bool
    {
        if (!empty($opts['force']) || empty($GLOBALS['modSettings']['ironnoob_ai_bots_budget_enabled'])) {
            return true;
        }

        $maxPerDay = self::settingInt('ironnoob_ai_bots_max_posts_per_day', 8, 0, 100);
        if (!$dryRun && $maxPerDay > 0 && (int) ($state['posts_today'] ?? 0) >= $maxPerDay) {
            self::log('Skipped: daily AI post budget reached (' . $state['posts_today'] . '/' . $maxPerDay . ').');
            return false;
        }

        if (self::isQuietHour()) {
            self::log('Skipped: quiet hours are active.');
            return false;
        }

        $minChance = self::settingInt('ironnoob_ai_post_chance_min', 30, 0, 100);
        $maxChance = self::settingInt('ironnoob_ai_post_chance_max', 70, 0, 100);
        if ($maxChance < $minChance) {
            $maxChance = $minChance;
        }
        $chance = random_int($minChance, $maxChance);
        $roll = random_int(1, 100);
        if ($roll > $chance) {
            self::log('Skipped: random posting budget roll ' . $roll . ' > chance ' . $chance . '.');
            return false;
        }

        return true;
    }

    private static function isQuietHour(): bool
    {
        $start = self::settingInt('ironnoob_ai_bots_quiet_start_hour', 0, 0, 23);
        $end = self::settingInt('ironnoob_ai_bots_quiet_end_hour', 8, 0, 23);
        if ($start === $end) {
            return false;
        }

        $hour = (int) date('G');
        if ($start < $end) {
            return $hour >= $start && $hour < $end;
        }

        return $hour >= $start || $hour < $end;
    }

    private static function dedupeEnabled(): bool
    {
        return !empty($GLOBALS['modSettings']['ironnoob_ai_bots_dedupe_enabled']);
    }

    private static function findSimilarOutput(string $body, array $state): ?array
    {
        if (!self::dedupeEnabled()) {
            return null;
        }

        $candidateTokens = self::dedupeTokens($body);
        $candidateNorm = self::normalizeForDedupe($body);
        if ($candidateNorm === '') {
            return ['score' => 100, 'reason' => 'empty'];
        }

        $threshold = self::settingInt('ironnoob_ai_bots_similarity_threshold', 58, 20, 100);
        foreach (($state['recent_outputs'] ?? []) as $entry) {
            $prevNorm = (string) ($entry['normalized'] ?? self::normalizeForDedupe((string) ($entry['body'] ?? '')));
            if ($prevNorm === '') {
                continue;
            }
            if ($candidateNorm === $prevNorm) {
                return ['score' => 100, 'reason' => 'exact'] + $entry;
            }

            $prevTokens = isset($entry['tokens']) && is_array($entry['tokens']) ? $entry['tokens'] : self::dedupeTokens((string) ($entry['body'] ?? ''));
            $score = self::tokenSimilarityScore($candidateTokens, $prevTokens);
            if ($score >= $threshold) {
                return ['score' => $score, 'reason' => 'tokens'] + $entry;
            }

            if (mb_strlen($candidateNorm) <= 300 && mb_strlen($prevNorm) <= 300) {
                similar_text($candidateNorm, $prevNorm, $percent);
                $charScore = (int) round($percent);
                if ($charScore >= max(76, $threshold + 15)) {
                    return ['score' => $charScore, 'reason' => 'chars'] + $entry;
                }
            }
        }

        return null;
    }

    private static function tokenSimilarityScore(array $a, array $b): int
    {
        if (empty($a) || empty($b)) {
            return 0;
        }

        $a = array_values(array_unique($a));
        $b = array_values(array_unique($b));
        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0 : (int) round(($intersection / $union) * 100);
    }

    private static function dedupeTokens(string $text): array
    {
        static $stopwords = ['the','and','for','that','this','with','you','your','are','but','just','like','lol','lmao','haha','yeah','fr','its','it\'s','honestly','really','kinda','sorta','very','they','them','there','have','has','was','were','what','when','where','who','why','how'];

        $norm = self::normalizeForDedupe($text);
        if ($norm === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $norm) ?: [];
        return array_values(array_unique(array_filter($tokens, static function ($token) use ($stopwords) {
            return mb_strlen($token) > 2 && !in_array($token, $stopwords, true);
        })));
    }

    private static function normalizeForDedupe(string $text): string
    {
        $text = strtolower(self::plain($text));
        $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private static function recentOutputAvoidancePrompt(): string
    {
        if (!self::dedupeEnabled()) {
            return '';
        }

        $state = self::loadState();
        $recent = array_slice($state['recent_outputs'] ?? [], 0, 5);
        if (empty($recent)) {
            return '';
        }

        $lines = [];
        foreach ($recent as $entry) {
            $lines[] = '- ' . self::excerpt((string) ($entry['body'] ?? ''), 160);
        }

        return " Avoid repeating the wording or vibe of these recent AI posts:\n" . implode("\n", $lines);
    }

    private static function loadState(): array
    {
        self::ensureRuntimeDirs();

        $state = [];
        if (is_file(self::STATE_FILE)) {
            $decoded = json_decode((string) file_get_contents(self::STATE_FILE), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        if (empty($state['recent_outputs'])) {
            $state['recent_outputs'] = self::seedRecentOutputsFromDatabase();
        }

        return self::pruneState($state);
    }

    private static function saveState(array $state): void
    {
        self::ensureRuntimeDirs();
        $state = self::pruneState($state);
        $tmp = self::STATE_FILE . '.tmp';
        file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        chmod($tmp, 0600);
        rename($tmp, self::STATE_FILE);
    }

    private static function resetDailyBudgetIfNeeded(array &$state): void
    {
        $today = date('Y-m-d');
        if (($state['budget_date'] ?? '') !== $today) {
            $state['budget_date'] = $today;
            $state['posts_today'] = self::countAiPostsSince(strtotime('today'));
        }
    }

    private static function recordPublishedDraft(array &$state, array $persona, array $draft): void
    {
        self::resetDailyBudgetIfNeeded($state);
        $state['posts_today'] = (int) ($state['posts_today'] ?? 0) + 1;
        array_unshift($state['recent_outputs'], [
            'time' => time(),
            'persona' => $persona['username'],
            'action' => $draft['action'] ?? '',
            'board' => $draft['board'] ?? null,
            'topic' => $draft['topic'] ?? null,
            'subject' => $draft['subject'] ?? '',
            'body' => $draft['body'] ?? '',
            'normalized' => self::normalizeForDedupe($draft['body'] ?? ''),
            'tokens' => self::dedupeTokens($draft['body'] ?? ''),
        ]);
    }

    private static function pruneState(array $state): array
    {
        $max = self::settingInt('ironnoob_ai_bots_dedupe_memory_size', 200, 10, 1000);
        $days = self::settingInt('ironnoob_ai_bots_dedupe_days', 30, 1, 365);
        $cutoff = time() - ($days * 86400);
        $recent = [];
        foreach (($state['recent_outputs'] ?? []) as $entry) {
            if ((int) ($entry['time'] ?? 0) >= $cutoff) {
                $recent[] = $entry;
            }
        }
        $state['recent_outputs'] = array_slice($recent, 0, $max);
        return $state;
    }

    private static function seedRecentOutputsFromDatabase(): array
    {
        global $smcFunc;

        if (empty($smcFunc) || !isset($smcFunc['db_query'])) {
            return [];
        }

        $limit = self::settingInt('ironnoob_ai_bots_dedupe_memory_size', 200, 10, 1000);
        $request = $smcFunc['db_query']('', '
            SELECT m.poster_time, mem.member_name, m.subject, m.body, m.id_board, m.id_topic
            FROM {db_prefix}messages AS m
                INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
            WHERE mem.member_name LIKE {string:ai_prefix}
            ORDER BY m.id_msg DESC
            LIMIT {int:limit}',
            ['ai_prefix' => self::USERNAME_PREFIX . '%', 'limit' => $limit]
        );

        $outputs = [];
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $outputs[] = [
                'time' => (int) $row['poster_time'],
                'persona' => $row['member_name'],
                'action' => 'seeded',
                'board' => (int) $row['id_board'],
                'topic' => (int) $row['id_topic'],
                'subject' => $row['subject'],
                'body' => $row['body'],
                'normalized' => self::normalizeForDedupe($row['body']),
                'tokens' => self::dedupeTokens($row['body']),
            ];
        }
        $smcFunc['db_free_result']($request);

        return $outputs;
    }

    private static function countAiPostsSince(int $timestamp): int
    {
        global $smcFunc;

        $request = $smcFunc['db_query']('', '
            SELECT COUNT(*)
            FROM {db_prefix}messages AS m
                INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
            WHERE mem.member_name LIKE {string:ai_prefix}
                AND m.poster_time >= {int:timestamp}',
            ['ai_prefix' => self::USERNAME_PREFIX . '%', 'timestamp' => $timestamp]
        );
        [$count] = $smcFunc['db_fetch_row']($request);
        $smcFunc['db_free_result']($request);

        return (int) $count;
    }

    private static function settingInt(string $key, int $default, int $min, int $max): int
    {
        $value = isset($GLOBALS['modSettings'][$key]) ? (int) $GLOBALS['modSettings'][$key] : $default;
        return max($min, min($max, $value));
    }

    private static function plain(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\[quote[^\]]*\].*?\[\/quote\]/is', '', $text);
        $text = preg_replace('/\[(?:\/)?[a-z0-9_*=-]+[^\]]*\]/i', '', $text);
        $text = strip_tags($text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private static function excerpt(string $text, int $length): string
    {
        $plain = self::plain($text);
        if (mb_strlen($plain) <= $length) {
            return $plain;
        }
        return rtrim(mb_substr($plain, 0, $length - 1)) . '…';
    }

    private static function truncateForLog(string $text): string
    {
        $text = str_replace(["\r", "\n"], [' ', ' '], $text);
        return mb_strlen($text) > 500 ? mb_substr($text, 0, 499) . '…' : $text;
    }

    private static function addHook(string $hook, string $function): void
    {
        add_integration_function($hook, $function, true, '$sourcedir/IronNoobAIBots.php');
    }

    private static function removeHook(string $hook, string $function): void
    {
        remove_integration_function($hook, $function, true, '$sourcedir/IronNoobAIBots.php');
    }

    private static function clampPostInt(string $key, int $min, int $max, int $default): int
    {
        $value = isset($_POST[$key]) ? (int) $_POST[$key] : $default;
        return max($min, min($max, $value));
    }

    private static function ensureRuntimeDirs(): void
    {
        foreach ([dirname(self::LOCK_FILE), dirname(self::LOG_FILE), dirname(self::STATE_FILE)] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
        }
    }

    private static function log(string $message): void
    {
        self::ensureRuntimeDirs();
        file_put_contents(self::LOG_FILE, '[' . date('c') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function loadText(): void
    {
        global $txt;

        $txt['ironnoob_ai_bots_title'] = 'IronNoob AI Bots';
        $txt['ironnoob_ai_bots_desc'] = 'Hourly AI forum posters. LLM credentials are stored outside the webroot. Keep dry-run enabled until generated posts look safe.';
        $txt['ironnoob_ai_bots_enabled'] = 'Enable hourly AI bot runner';
        $txt['ironnoob_ai_bots_dry_run'] = 'Dry run only; generate/log but do not create users or posts';
        $txt['ironnoob_ai_bots_new_topic_chance'] = 'New topic chance per run (%)';
        $txt['ironnoob_ai_bots_recent_topics'] = 'Recent topics to consider';
        $txt['ironnoob_ai_bots_context_messages'] = 'Recent messages to feed from selected thread';
        $txt['ironnoob_ai_bots_context_chars'] = 'Approximate character budget for forum context';
        $txt['ironnoob_ai_bots_num_ctx'] = 'Ollama num_ctx to request';
        $txt['ironnoob_ai_bots_new_topic_board'] = 'Board ID for new AI-started topics (0 = random public board)';
        $txt['ironnoob_ai_bots_max_body_chars'] = 'Maximum generated post body length';
        $txt['ironnoob_ai_bots_min_seconds_between_posts'] = 'Minimum seconds between live AI posts';
        $txt['ironnoob_ai_bots_budget_enabled'] = 'Enable random posting budget, daily cap, and quiet hours';
        $txt['ironnoob_ai_post_chance_min'] = 'Minimum hourly posting chance (%)';
        $txt['ironnoob_ai_post_chance_max'] = 'Maximum hourly posting chance (%)';
        $txt['ironnoob_ai_bots_max_posts_per_day'] = 'Maximum live AI posts per day';
        $txt['ironnoob_ai_bots_quiet_start_hour'] = 'Quiet hours start hour, 0-23';
        $txt['ironnoob_ai_bots_quiet_end_hour'] = 'Quiet hours end hour, 0-23';
        $txt['ironnoob_ai_bots_dedupe_enabled'] = 'Enable AI post similarity dedupe';
        $txt['ironnoob_ai_bots_dedupe_memory_size'] = 'AI post dedupe memory size';
        $txt['ironnoob_ai_bots_dedupe_days'] = 'AI post dedupe memory days';
        $txt['ironnoob_ai_bots_similarity_threshold'] = 'Similarity threshold for rejecting generated posts (%)';
        $txt['ironnoob_ai_bots_dedupe_retry_attempts'] = 'Generation attempts before skipping a duplicate-ish post';
    }
}
