<?php
declare(strict_types=1);

namespace HttpAnalyzer\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
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
        
        
        // Resolve event dispatcher from the App
        $event = app(Dispatcher::class);
        $event->listen(RequestHandled::class, EventListener::class);
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
    }
}