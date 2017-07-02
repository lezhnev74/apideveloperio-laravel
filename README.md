[![Packagist](https://img.shields.io/packagist/dt/lezhnev74/http-analyzer-laravel-adapter.svg)]()
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/lezhnev74/http-analyzer-laravel-adapter/master/LICENSE)
[![Build Status](https://travis-ci.org/lezhnev74/http-analyzer-laravel-adapter.svg?branch=master)](https://travis-ci.org/lezhnev74/http-analyzer-laravel-adapter)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/lezhnev74/http-analyzer-laravel-adapter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lezhnev74/http-analyzer-laravel-adapter/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/lezhnev74/http-analyzer-laravel-adapter/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/lezhnev74/http-analyzer-laravel-adapter/?branch=master)

# Laravel package to dump HTTP requests to your Dashboard
Laravel API adapter to track each http request app handled.

## Installation

### Version Compability
 Laravel  | Http analyzer version
:---------|:----------
 5.1.x    | 1.0.x
 5.2.x    | 2.0.x
 5.3.x    | 3.0.x
 5.4.x    | 4.0.x

### Steps
#### Install the package

```
composer require "lezhnev74/http-analyzer-laravel-adapter=~1.0"
```

#### Run this this command to publish configuration file to your `/config` folder.

```
php artisan vendor:publish --provider="HttpAnalyzer\Laravel\HttpAnalyzerServiceProvider"
```

#### Set-up cron command 
To dump recorded requests to the Dashboard. Open your `app/Console/Kernel.php` and add `DumpRecordedRequests::class` to commands list.

```php
#app/Console/Kernel.php
....
protected $commands = [
    ...
    'HttpAnalyzer\Laravel\DumpRecordedRequests\DumpRecordedRequests',
];

...

protected function schedule(Schedule $schedule)
{
    // you can set how often you want it to dump your requests to the Dashboard
    // every minute is the most frequent mode
    $schedule->command('http_analyzer:dump')->everyMinute();
}
```

## Configuration
After publishing, config file will be located at `config/http_analyzer.php` and speaks for himself.
The only required configuration is to put your API Key under `api_key` field.


## FAQ
#### How it works?
It hooks into Laravel app and records request, response and other data that you will see in your Dashboard:
* incoming request
* response
* database queries
* log entries

You can tweak which information you would like to send to the Dashboard.

The command `http_analyzer:dump` that you have set up will send all recorded requests to your Dashboard. 


#### I see no errors on the screen, but I don't see any requests in my dashboard. Why?
 
This package is designed to fail silently. If something went wrong while recording your requests - plugin won't interrupt your request lifecycle. Open your log and see if the package appended any critical information in there. 

Also check the tmp storage folder if there are any stale dump files.
 

## Support
Just open a new Issue here and get help.