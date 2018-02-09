<?php

namespace HttpAnalyzer\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\ServiceProvider;

final class HttpAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        /** @var Repository $config */
        $config = app()[Repository::class];
        
        $this->publishes([
            __DIR__ . '/../../config/http_analyzer.php' => config_path('http_analyzer.php'),
        ]);
        
        //
        // Hook on events
        //
        
        $event = app(Dispatcher::class);
        $event->listen(RequestHandled::class, function (RequestHandled $event) {
            $listener = app()[EventListener::class];
            $listener->onRequestHandled($event->request, $event->response);
        });
        $event->listen(QueryExecuted::class, EventListener::class . '@onDatabaseQueryExecuted');
        $event->listen(MessageLogged::class, function (MessageLogged $event) {
            $listener = app()[EventListener::class];
            $listener->onLog(
                $event->level,
                $event->message,
                $event->context
            );
        });
        
    }
    
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/http_analyzer.php', 'http_analyzer'
        );
        
        
        // Make sure event listener has just single instance
        $this->app->singleton(EventListener::class, function ($app) {
            return new EventListener(
                $app[Repository::class],
                $app[LoggerInterface::class]
            );
        });
        
        //
        // Prepare Guzzle Http Client to communicate with API backend
        //
        $this->app->bind(GuzzleHttpClient::class, function ($app) {
            $config = $app[Repository::class];
            
            $api_host = $config->get('http_analyzer.api_host', 'backend.apideveloper.io');
            $api_key  = $config->get('http_analyzer.api_key');
            
            return new GuzzleHttpClient([
                'base_uri' => 'https://' . $api_host,
                'http_errors' => false,
                'query' => ['api_key' => $api_key],
            ]);
        });
        
    }
}
