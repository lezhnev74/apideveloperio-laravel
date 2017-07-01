<?php
declare(strict_types=1);

namespace HttpAnalyzerTest;

use HttpAnalyzer\Laravel\EventListener;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Events\MessageLogged;
use Prophecy\Argument;
use Prophecy\Prediction\PredictionInterface;

final class PackageTest extends LaravelApp
{
    function test_it_subscribed_on_request_handled_event()
    {
        $stub                       = static::prophesize(EventListener::class);
        app()[EventListener::class] = $stub->reveal();
        
        $event = new RequestHandled(new Request(), new Response());
        $stub->onRequestHandled($event)->shouldBeCalled();
        
        app(Dispatcher::class)->dispatch($event);
    }
    
    function test_it_subscribed_on_query_executed_event()
    {
        $stub                       = static::prophesize(EventListener::class);
        app()[EventListener::class] = $stub->reveal();
        
        $event = new QueryExecuted(
            '',
            [],
            microtime(true),
            static::prophesize(Connection::class)
        );
        $stub->onDatabaseQueryExecuted($event)->shouldBeCalled();
        
        app(Dispatcher::class)->dispatch($event);
    }
    
    function test_it_subscribed_on_new_message_in_log_event()
    {
        $stub                       = static::prophesize(EventListener::class);
        app()[EventListener::class] = $stub->reveal();
        
        
        $stub->onLog(Argument::that(function (MessageLogged $m) {
            return $m->level == 'alert' &&
                   $m->message == 'message' &&
                   $m->context == ['a' => 2];
        }))->shouldBeCalled();
        
        app()['log']->alert("message", ['a' => 2]);
    }
    
}