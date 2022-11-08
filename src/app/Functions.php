<?php
function logger($message, $echo = true)
{
    $dir = getcwd() . '/var/logs/';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }

    @error_log(date('Y-m-d H:i:s') . "\t" . $message . PHP_EOL, 3, $dir . date('Ymd') . '.log');

    if ($echo) echo $message . PHP_EOL;
}

function ask($topic)
{
    echo $topic . "\r\n";
    $handle = fopen("php://stdin", "r");
    $input = fgets($handle);
    fclose($handle);
    return trim($input);
}

function isCliServer(): bool
{
    return php_sapi_name() == 'cli-server';
}

function failure($msg)
{
    logger($msg);
    if(!isCliServer()){
        $i = 0;
        while (true){
            sleep(10);
            $i++;
            if($i >= 60) exit;
        }
    }else{
        exit;
    }
}