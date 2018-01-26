<?php

namespace Apideveloper\Laravel\Tests\HTTP;

use Apideveloper\Laravel\Laravel\GuzzleHttpClient;
use Apideveloper\Laravel\Laravel\HTTP\DumpRecordedLogs;
use Apideveloper\Laravel\Laravel\HTTP\EventListener;
use Apideveloper\Laravel\Laravel\SendDumpsToDashboard;
use Apideveloper\Laravel\Tests\LaravelApp;
use Illuminate\Config\Repository;
use Illuminate\Console\Application;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prophecy\Argument;

final class HTTPLogTest extends LaravelApp
{

    function test_it_subscribed_on_request_handled_event()
    {
        $app = $this->createApplication();

        $stub                      = static::prophesize(EventListener::class);
        $app[EventListener::class] = $stub->reveal();

        $request  = new Request();
        $response = new Response();
        $stub->onRequestHandled($request, $response)->shouldBeCalled();

        $app[Dispatcher::class]->fire(new RequestHandled($request, $response));
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
        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $tmp_storage_path);

        $request  = new Request();
        $response = new Response();
        $app[Dispatcher::class]->fire(new RequestHandled($request, $response));

        // Make sure file is there

        $this->assertFileExists($tmp_storage_path . "/recorded_requests");

        // clean up
        unlink($tmp_storage_path . "/recorded_requests");
        rmdir($tmp_storage_path);
    }

    function test_it_dumps_multipart_post_data_to_file()
    {
        $app = $this->createApplication();

        /** @var Repository $config */
        $config           = app()[Repository::class];
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $tmp_storage_path);

        $data     = ['a' => 'some'];
        $request  = Request::create('/api/signup?b=12', 'POST', $data);
        $response = new Response();
        $app[Dispatcher::class]->fire(new RequestHandled($request, $response));

        // Make sure file is there

        $recorded_response = json_decode(trim(file_get_contents($tmp_storage_path . "/recorded_requests"), ','), true);
        $this->assertEquals($data, json_decode($recorded_response['http_request_body'], true));

        // clean up
        unlink($tmp_storage_path . "/recorded_requests");
        rmdir($tmp_storage_path);
    }

    function test_it_sends_dump_to_api_backend()
    {
        $console_app = new Application($this->app, $this->app[Dispatcher::class], "1");
        $config      = $console_app->getLaravel()[Repository::class];

        // I want to know that HTTP request was attempted to be send
        $fake_http_client = static::prophesize(GuzzleHttpClient::class);
        $fake_http_client
            ->request(
                Argument::is('POST'),
                Argument::is('/api/report/log'),
                Argument::is([
                    "headers" => ["content-type" => "application/json"],
                    "query" => ["api_key" => $config->set('apideveloperio_logs.httplog.api_key')],
                    "body" => '{"requests":[{"sample"=>"data"}]}',
                ])
            )
            ->willReturn(
                new \GuzzleHttp\Psr7\Response(200)
            )
            ->shouldBeCalled();
        $console_app->getLaravel()[GuzzleHttpClient::class] = $fake_http_client->reveal();

        $console_app->resolve(SendDumpsToDashboard::class);

        // Make up dump file
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $tmp_storage_path);
        file_put_contents($tmp_storage_path . "/recorded_requests", '{"sample"=>"data"},', FILE_APPEND);

        // Initiate a command
        $console_app->call('apideveloper:send-logs', ['--types' => 'http']);

        // clean up
        rmdir($tmp_storage_path);
    }

    function test_it_splits_dump_files_to_fit_the_size()
    {
        $app    = $this->createApplication();
        $config = $app[Repository::class];
        $config->set('apideveloperio_logs.httplog.dump_file_max_size', 1); // as little as possible
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $tmp_storage_path);

        // Now imitate $n requests and make sure n files are produces
        $n = rand(0, 100);
        for ($i = 0; $i < $n; $i++) {
            $app[Dispatcher::class]->fire(new RequestHandled(new Request(), new Response()));
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
        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $tmp_storage_path);
        $config->set('apideveloperio_logs.httplog.filtering.skip_url_matching_regexp', ['^/auth']);

        $request  = Request::create('/auth/signup');
        $response = new Response();
        $app[Dispatcher::class]->fire(new RequestHandled($request, $response));

        // Make sure file is there
        $this->assertFileNotExists($tmp_storage_path . "/recorded_requests");

        // cleanup
        rmdir($tmp_storage_path);
    }

    function test_it_will_skip_certain_http_methods()
    {
        $app = $this->createApplication();

        /** @var Repository $config */
        $config           = app()[Repository::class];
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $tmp_storage_path);
        $config->set('apideveloperio_logs.httplog.filtering.skip_http_methods', ['OPtiONS']);

        $request  = Request::create('/auth/signup', 'OPTIONs');
        $response = new Response();
        $app[Dispatcher::class]->fire(new RequestHandled($request, $response));

        // Make sure file is there
        $this->assertFileNotExists($tmp_storage_path . "/recorded_requests");

        // cleanup
        rmdir($tmp_storage_path);
    }

    function test_it_can_be_disabled()
    {
        $app = $this->createApplication();

        /** @var Repository $config */
        $config           = app()[Repository::class];
        $tmp_storage_path = $this->getTmpPath(__LINE__);
        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $tmp_storage_path);
        $config->set('apideveloperio_logs.httplog.enabled', false);

        $request  = Request::create('/auth/signup');
        $response = new Response();
        $app[Dispatcher::class]->fire(new RequestHandled($request, $response));

        // Make sure file is there
        $this->assertFileNotExists($tmp_storage_path . "/recorded_requests");

        // cleanup
        rmdir($tmp_storage_path);
    }

    function test_it_automatically_converts_query_bindings_to_strings()
    {
        $app = $this->createApplication();

        // Enable logging while testing
        /** @var Repository $config */
        $config = app()[Repository::class];
        $config->set('apideveloperio_logs.filtering.ignore_environment', []);
        $config->set('apideveloperio_logs.httplog.httplog.enabled', true);

        // Fake log writer
        // Log shoudl not be called because logging is only called on problem
        $log_prophecy = static::prophesize(Log::class);
        $log_prophecy->alert(Argument::cetera())->shouldNotBeCalled();
        $app[Log::class] = $log_prophecy->reveal();

        // Fake Connection
        $pdo_prophecy = static::prophesize(\PDO::class);
        $pdo_prophecy->getAttribute(\PDO::ATTR_DRIVER_NAME)->willReturn("A")->shouldBeCalled();
        $pdo_prophecy->getAttribute(\PDO::ATTR_SERVER_VERSION)->willReturn("B")->shouldBeCalled();

        $connection_prophecy = static::prophesize(Connection::class);
        $connection_prophecy->getName()->willReturn("default")->shouldBeCalled();
        $connection_prophecy->getPdo()->willReturn($pdo_prophecy->reveal())->shouldBeCalled();

        // Emit event
        $event = new QueryExecuted(
            'select * from users where created_at>?',
            [
                new \DateTime(),
            ],
            microtime(true),
            $connection_prophecy->reveal()
        );
        // imitate db request
        $app[Dispatcher::class]->fire($event);


    }
}