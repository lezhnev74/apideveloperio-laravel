<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 22/01/2018
 */

namespace Apideveloper\Laravel\Tests\Text;


use Apideveloper\Laravel\Laravel\Text\EventListener;
use Apideveloper\Laravel\Laravel\Text\ExceptionFormatter;
use Apideveloper\Laravel\Tests\LaravelApp;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Log\Writer;

class TextLogTest extends LaravelApp
{
    function test_it_catches_and_dumps_log_entries()
    {
        Carbon::setTestNow();
        $app              = $this->createApplication();
        $tmp_storage_path = $app[Repository::class]->get('apideveloperio_logs.textlog.tmp_storage_path');
        $listener         = $app[EventListener::class];

        $messages = ["ddd fff", "ztt rre"];
        foreach ($messages as $i => $message) {
            $app[Writer::class]->alert($message, ["message_index" => $i]);
        }

        // Check written logs
        $listener->flush(); // imitate end of life
        $this->assertFileExists($tmp_storage_path . "/buffered_text_logs");
        $file_contents = file_get_contents($tmp_storage_path . "/buffered_text_logs");
        $json_decoded  = json_decode("[" . trim($file_contents, ",") . "]", true);

        $this->assertEquals([
            [
                "level" => "alert",
                "message" => $messages[0],
                "context" => [
                    "message_index" => 0,
                ],
                "date" => Carbon::now()->toIso8601String(),
            ],
            [
                "level" => "alert",
                "message" => $messages[1],
                "context" => [
                    "message_index" => 1,
                ],
                "date" => Carbon::now()->toIso8601String(),
            ],
        ], $json_decoded[0]['messages']);

    }

    function test_it_logs_exceptions_in_separated_json_structure()
    {
        $app              = $this->createApplication();
        $tmp_storage_path = $app[Repository::class]->get('apideveloperio_logs.textlog.tmp_storage_path');
        $listener         = $app[EventListener::class];

        $previous_exception = new \DomainException("other message", 99);
        $e                  = new \Exception("", 100, $previous_exception);
        $app[Writer::class]->alert($e);

        // Check data
        $listener->flush(); // imitate end of life
        $file_contents = file_get_contents($tmp_storage_path . "/buffered_text_logs");
        $json_decoded  = json_decode("[" . trim($file_contents, ",") . "]", true);

        $this->assertCount(1, $json_decoded);
        $this->assertCount(1, $json_decoded[0]['messages']);
        $this->assertCount(2, $json_decoded[0]['messages'][0]['exception']);
        $this->assertEquals(ExceptionFormatter::fromException($e)->toArray(),
            $json_decoded[0]['messages'][0]['exception']);

    }

    function test_it_skip_logging_if_disabled()
    {
        $app              = $this->createApplication();
        $tmp_storage_path = $app[Repository::class]->get('apideveloperio_logs.textlog.tmp_storage_path');
        $listener         = $app[EventListener::class];
        $app[Repository::class]->set('apideveloperio_logs.textlog.enabled', false);
        $app[Writer::class]->alert("message sent");

        $listener = $this->app[EventListener::class];
        $listener->flush(); // imitate end of life
        $this->assertFileNotExists($tmp_storage_path . "/buffered_text_logs");

    }
}