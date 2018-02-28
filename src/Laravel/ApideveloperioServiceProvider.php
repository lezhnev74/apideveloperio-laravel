<?php

namespace Apideveloper\Laravel\Laravel;

use Apideveloper\Laravel\Backend\File\FileDumper;
use Apideveloper\Laravel\Backend\File\PersistingStrategy;
use Apideveloper\Laravel\Laravel\HTTP\EventListener as HTTPEventListener;
use Apideveloper\Laravel\Laravel\Text\EventListener as TextEventListener;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

final class ApideveloperioServiceProvider extends ServiceProvider
{

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/apideveloperio_logs.php' => config_path('apideveloperio_logs.php'),
        ], 'config');

        //
        // HTTP Log related
        //
        $this->listenHTTPRelatedEvents();

        //
        // TextLog related
        //
        $this->listenTextLogRelatedEvents();
    }

    protected function listenHTTPRelatedEvents()
    {
        $event = app(Dispatcher::class);

        if (Str::startsWith(app()->version(), ['5.2', '5.3'])) {
            // Here no object oriented events were available, so I have to listen to
            // legacy event names

            $event->listen('kernel.handled', function ($request, $response) {
                $listener = app()[HTTPEventListener::class];
                $listener->onRequestHandled($request, $response);
            });
            $event->listen(QueryExecuted::class, HTTPEventListener::class . '@onDatabaseQueryExecuted');
            $event->listen('illuminate.log', function ($level, $message, $context) {
                $listener = app()[HTTPEventListener::class];
                $listener->onLog($level, $message, $context);
            });
        } else {
            $event->listen(RequestHandled::class, function (RequestHandled $event) {
                $listener = app()[HTTPEventListener::class];
                $listener->onRequestHandled($event->request, $event->response);
            });
            $event->listen(QueryExecuted::class, HTTPEventListener::class . '@onDatabaseQueryExecuted');
            $event->listen(MessageLogged::class, function (MessageLogged $event) {
                $listener = app()[HTTPEventListener::class];
                $listener->onLog(
                    $event->level,
                    $event->message,
                    $event->context
                );
            });
        }
    }

    protected function listenTextLogRelatedEvents()
    {
        $event          = app(Dispatcher::class);
        $event_listener = app()[TextEventListener::class];

        if (Str::startsWith(app()->version(), ['5.2', '5.3'])) {
            $event->listen('illuminate.log', function ($level, $message, $context) use ($event_listener) {
                $event_listener->onLog($level, $message, $context);
            });
        } else {
            $event->listen(MessageLogged::class, function (MessageLogged $event) use ($event_listener) {
                $event_listener->onLog($event->level, $event->message, $event->context);
            });
        }
    }


    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $app_session_id = Uuid::uuid4()->toString();

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/apideveloperio_logs.php', 'apideveloperio_logs'
        );

        //
        // Prepare Guzzle Http Client to communicate with API backend
        //
        $this->app->bind(GuzzleHttpClient::class, function ($app) {
            $config = $app[Repository::class];

            $api_host            = $config->get('apideveloperio_logs.api_host', 'backend.apideveloper.io');
            $ignore_ssl_problems = $config->get('apideveloperio_logs.api_host_ignore_ssl', false);

            return new GuzzleHttpClient([
                'base_uri' => 'https://' . $api_host,
                'http_errors' => false,
                'verify' => !$ignore_ssl_problems,
            ]);
        });

        //
        // HTTPLog related
        //
        // Make sure event listener has just single instance
        $this->app->singleton(HTTPEventListener::class, function ($app) use ($app_session_id) {
            $config = $app[Repository::class];

            return new HTTPEventListener(
                $app_session_id,
                $app[Repository::class],
                $app[LoggerInterface::class],
                new FileDumper(new PersistingStrategy(
                    $config->get('apideveloperio_logs.httplog.tmp_storage_path', 'unknown_path'),
                    $config->get('apideveloperio_logs.httplog.dump_files_max_count', 100),
                    $config->get('apideveloperio_logs.httplog.dump_file_max_size', 10 * 1024 * 1024),
                    $config->get('apideveloperio_logs.httplog.dump_file_prefix', 'recorded_requests')
                ))
            );
        });

        //
        // Text log related
        //
        $this->app->singleton(TextEventListener::class, function ($app) use ($app_session_id) {
            $config = $app[Repository::class];

            return new TextEventListener(
                $app_session_id,
                new FileDumper(new PersistingStrategy(
                    $config->get('apideveloperio_logs.textlog.tmp_storage_path', 'unknown_path'),
                    $config->get('apideveloperio_logs.textlog.dump_files_max_count', 100),
                    $config->get('apideveloperio_logs.textlog.dump_file_max_size', 10 * 1024 * 1024),
                    $config->get('apideveloperio_logs.textlog.dump_file_prefix', 'buffered_text_logs')
                )),
                $app[Repository::class],
                $app[LoggerInterface::class]
            );
        });
        // Before app has been shut down, let's dump recorded logs into file
        $this->app->terminating(function () {
            $this->app[TextEventListener::class]->flush();
        });


    }
}