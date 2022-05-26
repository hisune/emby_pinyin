<?php
$parseUrl = parse_url('127.0.0.1');
if(!isset($parseUrl['scheme'])) $parseUrl['scheme'] = 'http';
if(!isset($parseUrl['port'])) $parseUrl['port'] = '8096';
$url = $parseUrl['scheme'] . '://' . ($parseUrl['host'] ?? $parseUrl['path']) . ':' . $parseUrl['port'];
var_dump($url);

exit;
require_once __DIR__ . '/app.phar';