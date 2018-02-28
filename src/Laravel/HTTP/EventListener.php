<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace Apideveloper\Laravel\Laravel\HTTP;


use Apideveloper\Laravel\Backend\File\LogsDumper;
use Apideveloper\Laravel\Backend\LoggedHTTPRequest;
use Illuminate\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;


/**
 * This class will record events and keep data in memory until request is complete and then
 * it saves recorded data for further transportation to API backend
 */
class EventListener
{

    /** @var array */
    private $recorded_data = [
        'external_queries' => [],
        'log_entries' => [],
    ];
    /** @var string */
    private $session_id;
    /** @var  Repository */
    private $config_repo;
    /** @var  LoggerInterface */
    private $log_writer;
    /** @var LogsDumper */
    private $dumper;

    /**
     * EventListener constructor.
     *
     * @param string $session_id ;
     * @param Repository $config_repo
     * @param Log $log
     * @param LogsDumper $dumper
     */
    public function __construct($session_id, Repository $config_repo, LoggerInterface $log, LogsDumper $dumper)
    {
        $this->session_id  = $session_id;
        $this->config_repo = $config_repo;
        $this->log_writer  = $log;
        $this->dumper      = $dumper;
    }


    public function onRequestHandled(Request $request, Response $response)
    {
        // If possible - calculate time duration
        $time_to_response = defined('LARAVEL_START') ?
            intval((microtime(true) - constant('LARAVEL_START')) * 1000) :
            1;

        try {
            if ($this->isRecordingDisabled() || $this->shouldSkipRequest($request)) {
                return;
            }

            //
            // Prepare packet with all recorded data
            //
            $logged_request = new LoggedHTTPRequest(
                $request,
                $response,
                $time_to_response,
                $this->session_id,
                implode("\n\n", $this->recorded_data['log_entries']),
                $this->recorded_data['external_queries'],
                $this->config_repo->get('apideveloperio_logs.httplog.filtering')
            );

            $this->saveRecordedRequest($logged_request);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function onDatabaseQueryExecuted(QueryExecuted $event)
    {
        try {
            if ($this->isRecordingDisabled()) {
                return;
            }

            $pdo    = $event->connection->getPdo();
            $vendor = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) .
                "/" .
                $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            // Laravel casting returns some items as DateTime objects
            foreach ($event->bindings as $key => $binding) {
                if (is_a($binding, 'DateTime')) {
                    $event->bindings[$key] = $binding->format('Y-m-d H:i:s');
                }
            }

            // Replace bindings with real values
            $sql = str_replace(['%', '?'], ['%%', '%s'], $event->sql);
            $sql = vsprintf($sql, $event->bindings);

            // Log this query
            $this->recorded_data['external_queries'][] = [
                "query" => $sql,
                "ttr_ms" => round($event->time), // round up to ms
                "type" => "database",
                "vendor" => $vendor,
            ];

        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function onLog($level, $message, $context)
    {
        try {
            if ($this->isRecordingDisabled()) {
                return;
            }

            $logged_message = $message . "\n";

            // if it can be jsonified - do it otherway just serialize
            if (($json = json_encode($context, JSON_UNESCAPED_UNICODE)) !== false) {
                $logged_message .= $json;
            } else {
                $logged_message .= serialize($context);
            }

            $this->recorded_data['log_entries'][] = $logged_message;
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }


    /**
     * saveRecordedRequest
     *
     *
     * @param LoggedHTTPRequest $request
     *
     * @throws \Exception
     * @return void
     */
    protected function saveRecordedRequest(LoggedHTTPRequest $request)
    {
        $this->dumper->dump($request->toArray());
    }

    /**
     * Detect if current environment is allowed to be recorded
     *
     *
     * @return bool
     */
    protected function isRecordingDisabled()
    {
        return in_array(
                app()->environment(),
                $this->config_repo->get('apideveloperio_logs.httplog.filtering.ignore_environment', [])
            ) || !$this->config_repo->get('apideveloperio_logs.httplog.enabled');
    }

    /**
     * Check if current request matches the filtering regexp
     *
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function shouldSkipRequest(Request $request)
    {
        $regexp_patterns   = $this->config_repo->get('apideveloperio_logs.httplog.filtering.skip_url_matching_regexp',
            []);
        $skip_http_methods = $this->config_repo->get('apideveloperio_logs.httplog.filtering.skip_http_methods');

        $should_skip = false;

        // check against HTTP method
        $should_skip = $should_skip ||
            in_array(strtoupper($request->method()), array_map('strtoupper', $skip_http_methods));

        // Check URL against regexps
        $should_skip = $should_skip ||
            (bool)count(array_filter($regexp_patterns, function ($pattern) use ($request) {
                return preg_match("#$pattern#", $request->getPathInfo());
            }));

        return $should_skip;
    }

    /**
     * Silently fail with log message
     *
     *
     * @param \Throwable $e
     *
     * @return void
     */
    protected function fail(\Throwable $e)
    {
        $this->log_writer->alert("Http analyzer failed", ['reason' => $e->getMessage()]);
    }


}
