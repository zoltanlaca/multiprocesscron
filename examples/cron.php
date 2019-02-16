<?php
/*
 * define your document root - in this example parent folder
 */
define('MY_DOC_ROOT', realpath(__DIR__ . '/..'));

/*
 * include MultiprocessCron class
 */
include_once(MY_DOC_ROOT . '/MultiProcessCron.class.php');

/*
 * create new MultiProcessCron object with a log file
 */
$cron = new MultiProcessCron(MY_DOC_ROOT.'/examples/cron.log');

/*
 * set the desired jobs
 */
$cron->setCronJob('php ' . MY_DOC_ROOT . '/examples/testfile.php', '0', '*', '*', '*', '*', '*');

/*
 * run the cron
 */
$cron->run();