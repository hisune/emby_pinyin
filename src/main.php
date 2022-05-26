<?php
use App\EmbyPinyin;

require_once __DIR__ . '/vendor/autoload.php';
$emby2pinyin = new EmbyPinyin();
$emby2pinyin->run();