[![Packagist](https://img.shields.io/packagist/dt/lezhnev74/apideveloperio-laravel.svg)]()
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/lezhnev74/apideveloperio-laravel/master/LICENSE)
[![Build Status](https://travis-ci.org/lezhnev74/apideveloperio-laravel.svg?branch=master)](https://travis-ci.org/lezhnev74/apideveloperio-laravel)

# Laravel package to record and send text logs and HTTP logs to your Apideveloper.io Dashboard
It works with both HTTP logs and normal text logs. It requires you to have an API Key to send recorded logs to your dashboard. HTTP logs and text logs require different keys.   

## Requirements
This package works with **PHP 5.6** and higher including (7.0, 7.1 and 7.2). It also supports **Laravel framework 5.2** and higher.

## Installation

### Steps
#### 1. Install the package

```
composer require "lezhnev74/apideveloperio-laravel=~6.0"
```

#### 2. Add service provider to your `config/app.php`
 
```php
    'providers' => [
        //...
        '\Apideveloper\Laravel\Laravel\ApideveloperioServiceProvider'
    ],
```

#### 3. Run this command to publish configuration file to your `/config` folder.

```
php artisan vendor:publish --provider="Apideveloper\Laravel\Laravel\ApideveloperioServiceProvider"
```

#### 4. Set-up CRON command 
To dump recorded logs to the Dashboard. Open your `app/Console/Kernel.php` and add class to commands list.

```php
#file: app/Console/Kernel.php
....
protected $commands = [
    //...
    '\Apideveloper\Laravel\Laravel\SendDumpsToDashboard',
];

...

protected function schedule(Schedule $schedule)
{
    // you can set how often you want it to dump your data to the Dashboard
    // every minute is the most frequent mode
    $schedule->command('apideveloper:send-logs')->everyMinute();
}
```

#### 5. That's it!

## Configuration
After publishing, the config file will be located at `config/apideveloperio_logs.php` and speaks for himself.
The only required configuration is to put your API Key under `api_key` field for both `httplog` and `textlog` sections.
You can disable/enable HTTP logging as well as textual logging independently.


## FAQ
#### How it works?
It hooks into Laravel app and records request, response and text logs that you will see on your Dashboard:
* incoming request
* response
* database queries
* log entries
* exceptions (including stack traces)

You can tweak which information you would like to send to the Dashboard using the config file.

The command `apideveloper:send-logs` that you have set up will send all recorded logs to your Dashboard. 


#### I see no errors on the screen, but I don't see any requests on my dashboard. Why?
 
This package is designed to fail silently. If something went wrong while recording your requests - plugin won't interrupt your request lifecycle. 
Open your log and see if the package appended any critical information in there. 

Also, check the tmp storage folder if there are any stale dump files.
 

## Suggestions
#### If user IPs are always 127.0.0.1

That happens due to some Symfony's Request issue. Try using this package - https://github.com/fideloper/TrustedProxy. Should do the trick. 

#### What is the best way to track each request?
When someone refers to particular request/response app cycle, it is best to know it's unique ID.
 Knowing it, you can easily find it on the Dashboard.
 Just add a middleware (like this one https://github.com/softonic/laravel-middleware-request-id) which will append a unique ID to each response your app provides.

## 🏆 Contributors
- [Owen Melbourne](https://github.com/OwenMelbz) - improved dates conversions
- [Mark Topper](https://github.com/marktopper) - added support for Laravel 5.6

## Support
Just open a new Issue here and get help.