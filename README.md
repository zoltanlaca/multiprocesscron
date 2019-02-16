# MultiProcessCron
A simple PHP class that partly replaces crontab running in one process. Triggers multiple unique processes at once, as needed.

How to use
----


Define your document root - in this example parent folder
```php
define('MY_DOC_ROOT', realpath(__DIR__ . '/..'));
```

Include MultiProcessCron class
```php
include_once(MY_DOC_ROOT . '/MultiProcessCron.class.php');
```

Create new MultiProcessCron object with a log file,
```php
$cron = new MultiProcessCron(MY_DOC_ROOT.'/examples/cron.log');
```
or without log file
```php
$cron = new MultiProcessCron();
```

Set the desired jobs
```php
$cron->setCronJob('php ' . MY_DOC_ROOT . '/examples/testfile.php', '0', '*', '*', '*', '*', '*');
```
Run the cron
 ```php
$cron->run();
```

#### Allowed cron rule formats for setCronJob params:
* any value: '*'
* value list separator: ',', for example. '2,4,6'
* range of values: '-', for example '2-6'
* step values: '/', for example '*/2'

License
----

MIT