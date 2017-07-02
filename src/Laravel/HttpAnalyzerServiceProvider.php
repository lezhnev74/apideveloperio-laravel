<?php
declare(strict_types=1);

namespace HttpAnalyzer\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
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
        $this->publishes([
            __DIR__ . '/../../config/http_analyzer.php' => config_path('http_analyzer.php'),
        ]);
        
        //
        // Hook on events
        //
        $event = app(Dispatcher::class);
        $event->listen('kernel.handled', EventListener::class . '@onRequestHandled');
        $event->listen(QueryExecuted::class, EventListener::class . '@onDatabaseQueryExecuted');
        $event->listen('illuminate.log', EventListener::class . '@onLog');
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
        
        //
        // Prepare Guzzle Http Client to communicate with API backend
        //
        $this->app->bind(GuzzleHttpClient::class, function ($app) {
            $config = $app[Repository::class];
            
            $api_host = $config->get('http_analyzer.api_host', 'app.lessthan12ms.com');
            $api_key  = $config->get('http_analyzer.api_key');
            
            return new GuzzleHttpClient([
                'base_uri' => 'https://' . $api_host,
                'http_errors' => false,
                'query' => ['api_key' => $api_key],
            ]);
        });
    }
}