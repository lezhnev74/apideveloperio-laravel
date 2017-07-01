<?php
declare(strict_types=1);

namespace HttpAnalyzerTest;

use HttpAnalyzer\Laravel\EventListener;
use Illuminate\Config\Repository;
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
    
    function test_it_dumps_data_to_file()
    {
        /** @var Repository $config */
        $config           = app()[Repository::class];
        $tmp_storage_path = __DIR__ . "/tmp/" . time();
        $config->set('http_analyzer.tmp_storage_path', $tmp_storage_path);
        
        $event = new RequestHandled(new Request(), new Response());
        app(Dispatcher::class)->dispatch($event);
        
        // Make sure file is there
        $this->assertDirectoryExists($tmp_storage_path);
        $this->assertFileExists($tmp_storage_path . "/recorded_requests");
        
        // clean up
        unlink($tmp_storage_path . "/recorded_requests");
        rmdir($tmp_storage_path);
    }
}