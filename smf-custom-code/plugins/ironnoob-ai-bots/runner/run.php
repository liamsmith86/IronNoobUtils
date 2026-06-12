<?php

// CLI-only runner for IronNoobAIBots. Keep this outside the public webroot.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['is_cli'] = true;
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['BAN_CHECK_IP'] = $_SERVER['BAN_CHECK_IP'] ?? '127.0.0.1';
$_SERVER['SERVER_PROTOCOL'] = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['QUERY_STRING'] = $_SERVER['QUERY_STRING'] ?? '';
$_SERVER['PHP_SELF'] = $_SERVER['PHP_SELF'] ?? __FILE__;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'ironnoob.net';
$_SERVER['SERVER_SOFTWARE'] = $_SERVER['SERVER_SOFTWARE'] ?? 'cli';

require_once '/home/liamsmit/ironnoob.net/SSI.php';
require_once $sourcedir . '/IronNoobAIBots.php';

exit(IronNoobAIBots::runFromCli($argv));
