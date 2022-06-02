<?php
$phar = new phar('app.phar');
$phar->buildFromDirectory(__DIR__.'/src');
$phar->compressFiles(phar::GZ);
$phar->stopBuffering();
$phar->setStub($phar->createDefaultStub('main.php'));