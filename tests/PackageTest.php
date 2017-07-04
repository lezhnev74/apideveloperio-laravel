<?php

namespace HttpAnalyzerTest;

use HttpAnalyzer\Laravel\DumpRecordedRequests;
use HttpAnalyzer\Laravel\EventListener;
use HttpAnalyzer\Laravel\GuzzleHttpClient;
use Illuminate\Config\Repository;
use Illuminate\Console\Application;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JMS\Serializer\SerializerBuilder;
use Prophecy\Argument;

final class PackageTest extends LaravelApp
{
    function test_it_subscribed_on_request_handled_event()
    {
        $app = $this->createApplication();
        
        $stub                      = static::prophesize(EventListener::class);
        $app[EventListener::class] = $stub->reveal();
        
        $request  = new Request();
        $response = new Response();
        $stub->onRequestHandled($request, $response)->shouldBeCalled();
        
        $app[Dispatcher::class]->fire('kernel.handled', [$request, $response]);
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
        
        $app[Dispatcher::class]->fire($event);
    }
    
    function test_it_subscribed_on_new_message_in_log_event()
    {
        $app = $this->createApplication();
        
        $stub                      = static::prophesize(EventListener::class);
        $app[EventListener::class] = $stub->reveal();
        
        
        $stub->onLog(
            Argument::is('alert'),
            Argument::is('message'),
            Argument::is(['a' => 2])
        )->shouldBeCalled();
        
        $app['log']->alert("message", ['a' => 2]);
    }
    
    
    function test_it_dumps_data_to_file()
    {
        $app = $this->createApplication();
        
        /** @var Repository $config */
        $config           = app()[Repository::class];
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('http_analyzer.tmp_storage_path', $tmp_storage_path);
        
        $request  = new Request();
        $response = new Response();
        $app[Dispatcher::class]->fire('kernel.handled', [$request, $response]);
        
        // Make sure file is there
        
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
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('http_analyzer.tmp_storage_path', $tmp_storage_path);
        file_put_contents($tmp_storage_path . "/recorded_requests", '{"sample"=>"data"},', FILE_APPEND);
        
        // Initiate a command
        $console_app->call('http_analyzer:dump');
        
        // clean up
        rmdir($tmp_storage_path);
    }
    
    function test_it_splits_dump_files_to_fit_the_size()
    {
        $app    = $this->createApplication();
        $config = $app[Repository::class];
        $config->set('http_analyzer.dump_file_max_size', 1); // as little as possible
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('http_analyzer.tmp_storage_path', $tmp_storage_path);
        
        // Now imitate $n requests and make sure n files are produces
        $n = rand(0, 100);
        for ($i = 0; $i < $n; $i++) {
            $app[Dispatcher::class]->fire('kernel.handled', [new Request(), new Response()]);
        }
        
        $files_in_tmp_folder = array_filter(scandir($tmp_storage_path), function ($file) use ($tmp_storage_path) {
            return is_file($tmp_storage_path . "/" . $file);
        });
        
        $this->assertEquals($n, count($files_in_tmp_folder));
        
        // clean up
        array_walk($files_in_tmp_folder, function ($file) use ($tmp_storage_path) {
            unlink($tmp_storage_path . "/" . $file);
        });
        rmdir($tmp_storage_path);
    }
    
    function test_it_skips_regex_urls()
    {
        $app = $this->createApplication();
        
        /** @var Repository $config */
        $config           = app()[Repository::class];
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('http_analyzer.tmp_storage_path', $tmp_storage_path);
        $config->set('http_analyzer.filtering.skip_url_matching_regexp', ['^/auth']);
        
        $request  = Request::create('/auth/signup');
        $response = new Response();
        $app[Dispatcher::class]->fire('kernel.handled', [$request, $response]);
        
        // Make sure file is there
        $this->assertFileNotExists($tmp_storage_path . "/recorded_requests");
    }
}