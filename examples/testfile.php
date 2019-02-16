<?php
/*
 * Only test file, random sleeep for a small execution time
 */
$sleepTime = rand(10, 20);

sleep($sleepTime);

echo 'Hello world! This script has a ' . $sleepTime .' second execution time.'.PHP_EOL;