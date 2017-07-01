<?php
declare(strict_types=1);

namespace HttpAnalyzerTest;

use HttpAnalyzer\EventListener;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class FirstTest extends LaravelApp
{
    function test_it_hooks_int_laravel_app_lifecircle()
    {
        $stub                       = static::prophesize(EventListener::class);
        app()[EventListener::class] = $stub->reveal();
        
        $event = new RequestHandled(new Request(), new Response());
        $stub->handle($event)->shouldBeCalled();
        
        app(Dispatcher::class)->dispatch($event);
    }
}