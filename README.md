[![Packagist](https://img.shields.io/packagist/dt/lezhnev74/apideveloperio-laravel.svg)]()
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/lezhnev74/apideveloperio-laravel/master/LICENSE)
[![Build Status](https://travis-ci.org/lezhnev74/apideveloperio-laravel.svg?branch=laravel-53)](https://travis-ci.org/lezhnev74/apideveloperio-laravel)

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
#### 1. Install the package

```
composer require "lezhnev74/apideveloperio-laravel=~3.0"
```

#### 2. Add service provider to your `config/app.php`
 
```php
    'providers' => [
        ...
        '\HttpAnalyzer\Laravel\HttpAnalyzerServiceProvider'
    ],
```

#### 3. Run this this command to publish configuration file to your `/config` folder.

```
php artisan vendor:publish --provider="HttpAnalyzer\Laravel\HttpAnalyzerServiceProvider"
```

#### 4. Set-up cron command 
To dump recorded requests to the Dashboard. Open your `app/Console/Kernel.php` and add class to commands list.

```php
#app/Console/Kernel.php
....
protected $commands = [
    ...
    '\HttpAnalyzer\Laravel\DumpRecordedRequests',
];

...

protected function schedule(Schedule $schedule)
{
    // you can set how often you want it to dump your requests to the Dashboard
    // every minute is the most frequent mode
    $schedule->command('http_analyzer:dump')->everyMinute();
}
```

#### 5. That's it!

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
 

## Suggestions
#### If user IPs are always 127.0.0.1

That happens due to some Symfony's Request issue. Try using this package - https://github.com/fideloper/TrustedProxy. Should do the trick. 

#### What is the best way to track each request?
When someone refers to particular request/response app cycle, it is best to know it's unique ID.
 Knowing it you can easily find it in the Dashboard.
 Just add a middleware (like this one https://github.com/softonic/laravel-middleware-request-id) which will append a unique ID to each response your app provides.

## Support
Just open a new Issue here and get help.