<?php
declare(strict_types=1);

namespace HttpAnalyzerTest;

use HttpAnalyzer\Laravel\DumpRecordedRequests;
use HttpAnalyzer\Laravel\EventListener;
use HttpAnalyzer\Laravel\GuzzleHttpClient;
use Illuminate\Config\Repository;
use Illuminate\Console\Application;
use Illuminate\Contracts\Console\Kernel;
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
        $app = $this->createApplication();
        
        $stub                      = static::prophesize(EventListener::class);
        $app[EventListener::class] = $stub->reveal();
        
        $event = new RequestHandled(new Request(), new Response());
        $stub->onRequestHandled($event)->shouldBeCalled();
        
        $app[Dispatcher::class]->dispatch($event);
    }
    
    function test_it_subscribed_on_query_executed_event()
    {
        $app = $this->createApplication();
        
        $stub                      = static::prophesize(EventListener::class);
        $app[EventListener::class] = $stub->reveal();
        
        $event = new QueryExecuted(
            '',
            [],
            microtime(true),
            static::prophesize(Connection::class)
        );
        $stub->onDatabaseQueryExecuted($event)->shouldBeCalled();
        
        $app[Dispatcher::class]->dispatch($event);
    }
    
    function test_it_subscribed_on_new_message_in_log_event()
    {
        $app = $this->createApplication();
        
        $stub                      = static::prophesize(EventListener::class);
        $app[EventListener::class] = $stub->reveal();
        
        
        $stub->onLog(Argument::that(function (MessageLogged $m) {
            return $m->level == 'alert' &&
                   $m->message == 'message' &&
                   $m->context == ['a' => 2];
        }))->shouldBeCalled();
        
        $app['log']->alert("message", ['a' => 2]);
    }
    
    function test_it_dumps_data_to_file()
    {
        $app = $this->createApplication();
        
        /** @var Repository $config */
        $config           = app()[Repository::class];
        $tmp_storage_path = __DIR__ . "/tmp/" . time();
        $config->set('http_analyzer.tmp_storage_path', $tmp_storage_path);
        
        $event = new RequestHandled(new Request(), new Response());
        $app[Dispatcher::class]->dispatch($event);
        
        // Make sure file is there
        $this->assertDirectoryExists($tmp_storage_path);
        $this->assertFileExists($tmp_storage_path . "/recorded_requests");
        
        // clean up
        unlink($tmp_storage_path . "/recorded_requests");
        rmdir($tmp_storage_path);
    }
    
    function test_it_sends_dump_to_api_backend()
    {
        $console_app = new Application($this->app, $this->app[Dispatcher::class], "1");
        
        // I want to know that HTTP request was attempted to be send
        $fake_http_client = static::prophesize(GuzzleHttpClient::class);
        $fake_http_client
            ->request(
                Argument::is('POST'),
                Argument::is('/api/report/log'),
                Argument::is([
                    "headers" => ["content-type" => "application/json"],
                    "body" => '{"requests":[{"sample"=>"data"}]}',
                ])
            )
            ->willReturn(
                new \GuzzleHttp\Psr7\Response(200)
            )
            ->shouldBeCalled();
        $console_app->getLaravel()[GuzzleHttpClient::class] = $fake_http_client->reveal();
        
        $console_app->resolve(DumpRecordedRequests::class);
        $config = $console_app->getLaravel()[Repository::class];
        
        // Make up dump file
        $tmp_storage_path = __DIR__ . "/tmp/" . time();
        mkdir($tmp_storage_path);
        $config->set('http_analyzer.tmp_storage_path', $tmp_storage_path);
        file_put_contents($tmp_storage_path . "/recorded_requests", '{"sample"=>"data"},', FILE_APPEND);
        
        // Initiate a command
        $console_app->call('http_analyzer:dump');
        
        // clean up
        rmdir($tmp_storage_path);
    }
}