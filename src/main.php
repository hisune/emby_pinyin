<?php
use App\EmbyPinyin;

require_once __DIR__ . '/vendor/autoload.php';
$embyPinyin = new EmbyPinyin();
$embyPinyin->run();