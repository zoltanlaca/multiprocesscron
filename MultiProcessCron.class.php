<?php

/**
 * Class MultiProcessCron
 * @author  Zoltan Laca
 * @copyright 2019 Zoltan Laca
 * @license MIT https://github.com/zoltanlaca/multiprocesscron/blob/master/LICENSE
 * @link https://github.com/zoltanlaca/multiprocesscron
 */
class MultiProcessCron
{
    /**
     * @var string
     */
    protected $logFile;
    /**
     * @var array
     */
    private $jobs = [];
    /**
     * @var array
     */
    private $runningJobs = [];
    /**
     * @var float
     */
    private $runTimeStart;

    /**
     * MultiProcessCron constructor.
     */
    function __construct($logFile = '/dev/null')
    {
        $this->setRunTime();
        $this->setLogFile($logFile);
        $this->log('Hi! MultiProcessCron initialized.');
    }

    /**
     *
     */
    private function setRunTime(): void
    {
        /*
         * set the cron start time
         */
        $this->runTimeStart = microtime(true);
    }

    /**
     * @param string $logFile
     */
    public function setLogFile(string $logFile)
    {
        /*
         * set the cron log file path
         */
        $this->logFile = $logFile;
    }

    /**
     * @param string $message
     */
    private function log(string $message): void
    {
        /*
         * Prepare log message string
         */
        $dateTime = New \DateTime();
        $logMessage = sprintf('%s [%ss] %s', $dateTime->format('Y-m-d H:i:s'), $this->getRunTimeSeconds(), $message . PHP_EOL);

        /*
         * Echo the log message to console
         */
        echo $logMessage;

        /*
         * If isset log file path, write the log message to it
         */
        if ($this->logFile != '/dev/null') {
            file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @return int
     */
    private function getRunTimeSeconds(): int
    {
        /*
         * get the cron run time in seconds
         */
        return round(microtime(true) - $this->runTimeStart, 2);
    }

    /**
     * @param string $cmd
     * @param string $second
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $weekday
     * @param int|null $killAfterSecond
     */
    public function setCronJob(string $cmd, string $second, string $minute, string $hour, string $day, string $month, string $weekday, int $killAfterSecond = null): void
    {
        /*
         * Set the details for new cron job. Possible formats, like crontab syntax:
         * - any value '*'
         * - value list separator ','
         * - range of values '-',
         * - step values '/'
         */
        $cronJob = new \stdClass();
        $cronJob->cmd = $cmd;
        $cronJob->second = $second;
        $cronJob->minute = $minute;
        $cronJob->hour = $hour;
        $cronJob->day = $day;
        $cronJob->month = $month;
        $cronJob->weekday = $weekday;
        $cronJob->killAfterSeconds = $killAfterSecond;
        $cronJob->lastRun = null;
        $this->jobs[] = $cronJob;
    }

    /**
     * @param int|null $maxRunSecondLimit
     */
    public function run(int $maxRunSecondLimit = null): void
    {
        /*
         * run the cron, while time limit not reached (unlimited when null)
         */
        while ($maxRunSecondLimit == null OR $this->getRunTimeSeconds() < $maxRunSecondLimit) {

            $this->unsetNotRunningJobs();

            /*
             * run each non-running cron jobs
             */
            foreach ($this->jobs as $jobKey => $cronJob) {

                if ( !array_key_exists($jobKey, $this->runningJobs)
                    AND $this->haveRunNow($cronJob->second, $cronJob->minute, $cronJob->hour, $cronJob->day, $cronJob->month, $cronJob->weekday)
                    AND $this->jobs[$jobKey]->lastRun != time()
                ) {
                    $newRunningJob = New \stdClass();
                    $newRunningJob->pid = $this->processStart($cronJob->cmd);
                    $newRunningJob->startTime = microtime(true);
                    $this->runningJobs[$jobKey] = $newRunningJob;
                    $this->jobs[$jobKey]->lastRun = time();
                }
            }
            time_nanosleep(0, 500000000);
        }

        /*
         * check if the job running, wait for it
         */
        $this->log('Reached max execution time, ' . count($this->runningJobs) . ' process running...');
        while (count($this->runningJobs) != 0) {
            $this->unsetNotRunningJobs();
            sleep(1);
        }
        /*
         * end the cron with bye message
         */
        $this->log('MultiProcessCron successfully finished. Bye!');
    }

    /**
     *
     */
    private function unsetNotRunningJobs(): void
    {
        /*
         * check for running job
         */
        foreach ($this->runningJobs as $runningJobKey => $runningJob) {
            if (!$this->processStatus($runningJob->pid)) {
                /*
                 * when not running, unset it
                 */
                $this->log('Process [' . $this->jobs[$runningJobKey]->cmd . '] with PID: ' . $runningJob->pid . ' not running.');
                unset($this->runningJobs[$runningJobKey]);
            } elseif ($this->jobs[$runningJobKey]->killAfterSeconds != null AND (microtime(true) - $runningJob->startTime) > $this->jobs[$runningJobKey]->killAfterSeconds) {
                /*
                 * when run longer than defined, kill it
                 */
                $this->log('Process [' . $this->jobs[$runningJobKey]->cmd . '] with PID: ' . $runningJob->pid . ' reached execution time limit.');
                if ($this->processStop($runningJob->pid)) {
                    unset($this->runningJobs[$runningJobKey]);
                }
            }
        }
    }

    /**
     * @param int $pid
     * @return bool
     */
    private function processStatus(int $pid)
    {
        /*
         * check if process running
         */
        exec('ps -p ' . $pid, $output);
        if (!isset($output[1])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param int $pid
     * @return bool
     */
    private function processStop(int $pid): bool
    {
        exec('kill ' . $pid);
        if ($this->processStatus($pid) == false) {
            $this->log('Process with PID: ' . $pid . ' killed.');
            return true;
        } else {
            $this->log('ERROR: Process with PID: ' . $pid . ' NOT killed!');
            return false;
        }
    }

    /**
     * @param string $second
     * @param string $minute
     * @param string $hour
     * @param string $day
     * @param string $month
     * @param string $weekday
     * @return bool
     */
    private function haveRunNow(string $second, string $minute, string $hour, string $day, string $month, string $weekday): bool
    {
        $toCheck = [
            'second' => 's',
            'minute' => 'i',
            'hour' => 'G',
            'day' => 'j',
            'month' => 'n',
            'weekday' => 'w',
        ];

        $ranges = [
            'second' => '0-59',
            'minute' => '0-59',
            'hour' => '0-23',
            'day' => '1-31',
            'month' => '1-12',
            'weekday' => '0-6',
        ];

        foreach ($toCheck as $part => $c) {
            $val = $$part;
            $values = [];

            /*
             * For patters like 0-23/2
             */
            if (strpos($val, '/') !== false) {
                /*
                 * Get the range and step
                 */
                list($range, $steps) = explode('/', $val);

                /*
                 * Now get the start and stop
                 */
                if ($range == '*') {
                    $range = $ranges[$part];
                }
                list($start, $stop) = explode('-', $range);

                for ($i = $start; $i <= $stop; $i = $i + $steps) {
                    $values[] = $i;
                }
            }

            /*
             * For patters like :
             * 2
             * 2,5,8
             * 2-23
             */
            else {
                $k = explode(',', $val);

                foreach ($k as $v) {
                    if (strpos($v, '-') !== false) {
                        list($start, $stop) = explode('-', $v);

                        for ($i = $start; $i <= $stop; $i++) {
                            $values[] = $i;
                        }
                    } else {
                        $values[] = $v;
                    }
                }
            }

            if (!in_array(date($c, time()), $values) and (strval($val) != '*')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $cmd
     * @return int
     */
    private function processStart($cmd): int
    {
        exec('nohup ' . $cmd . ' >> ' . $this->logFile . ' 2>&1 & echo $!', $output);
        $pid = (int)$output[0];
        $this->log('Starting cmd: [' . $cmd . '], PID:' . $pid . '.');
        return $pid;
    }

}