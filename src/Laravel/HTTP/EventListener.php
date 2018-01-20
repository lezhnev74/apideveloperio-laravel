<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace Apideveloper\Laravel\Laravel\HTTP;


use Apideveloper\Laravel\Backend\LoggedHTTPRequest;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
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
    /** @var  Repository */
    private $config_repo;
    /** @var  Log */
    private $log_writer;
    
    /**
     * EventListener constructor.
     *
     * @param Repository $config_repo
     */
    public function __construct(Repository $config_repo, Log $log)
    {
        $this->config_repo = $config_repo;
        $this->log_writer  = $log;
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
                implode("\n\n", $this->recorded_data['log_entries']),
                $this->recorded_data['external_queries'],
                $this->config_repo->get('http_analyzer.filtering')
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
        $tmp_path_folder = $this->config_repo->get('http_analyzer.tmp_storage_path');
        
        //
        // Now persist data till the next data dump to the API backend
        //
        if (!is_dir($tmp_path_folder) && !mkdir($tmp_path_folder)) {
            throw new \Exception("Unable to create a directory for storing recorded requests at " . $tmp_path_folder);
        }
        
        //
        // If there are too many dumped un-sent files then stop recording
        //
        $max_files_count = (int)$this->config_repo->get('http_analyzer.dump_files_max_count', 100);
        $dump_files      = array_filter(scandir($tmp_path_folder), function ($file) {
            return strpos($file, "recorded_requests") !== false;
        });
        if (count($dump_files) >= $max_files_count) {
            throw new \Exception("Maximum count of dump files reached. Recording stopped.");
        }
        
        //
        // make a file to dump every request to
        //
        $file_path     = $tmp_path_folder . "/recorded_requests";
        $max_file_size = (int)$this->config_repo->get('http_analyzer.dump_file_max_size', 10 * 1024 * 1024);
        if (file_exists($file_path) && filesize($file_path) > $max_file_size) {
            // rename it and write to a fresh one
            rename($file_path, $file_path . "_batch_" . date("d-m-Y_H_i_s") . "_" . str_random(8));
        }
        
        //
        // Dump it
        //
        file_put_contents($file_path, $request->toJson() . ",", FILE_APPEND | LOCK_EX);
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
                   $this->config_repo->get('http_analyzer.filtering.ignore_environment', [])
               ) || !$this->config_repo->get('http_analyzer.enabled');
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
        $regexp_patterns   = $this->config_repo->get('http_analyzer.filtering.skip_url_matching_regexp', []);
        $skip_http_methods = $this->config_repo->get('http_analyzer.filtering.skip_http_methods');
        
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
