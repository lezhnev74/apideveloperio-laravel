<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace HttpAnalyzer\Laravel;


use HttpAnalyzer\Backend\LoggedRequest;
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
        try {
            if ($this->isRecordingDisabled() || $this->shouldSkipRequest($request)) {
                return;
            }
            
            // If possible - calculate time duration
            $time_to_response = defined('LARAVEL_START') ?
                intval((microtime(true) - constant('LARAVEL_START')) * 1000) :
                1;
            
            //
            // Prepare packet with all recorded data
            //
            
            $logged_request = new LoggedRequest(
                $request,
                $response,
                $time_to_response,
                implode("\n\n", $this->recorded_data['log_entries']),
                $this->recorded_data['external_queries'],
                $this->config_repo->get('http_analyzer.filtering')
            );
            
            $tmp_storage_path = $this->config_repo->get('http_analyzer.tmp_storage_path');
            $this->saveRecordedRequest($logged_request, $tmp_storage_path);
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
            
            // Log this query
            $this->recorded_data['external_queries'][] = [
                "query" => $event->sql,
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
            
            $logged_message = $message . " context: ";
            
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
     * @param LoggedRequest $request
     * @param string        $tmp_path_folder to use for temp storing
     *
     * @throws \Exception
     * @return void
     */
    protected function saveRecordedRequest(LoggedRequest $request, $tmp_path_folder)
    {
        //
        // Now persist data till the next data dump to the API backend
        //
        if (!is_dir($tmp_path_folder) && !mkdir($tmp_path_folder)) {
            throw new \Exception("Unable to create a directory for storing recorded requests at " . $tmp_path_folder);
        }
        
        //
        // make a file to dump every request to
        //
        $file_path = $tmp_path_folder . "/recorded_requests";
        
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
        );
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
        $regexp_patterns = $this->config_repo->get('http_analyzer.filtering.skip_url_matching_regexp', []);
        
        return (bool)count(array_filter($regexp_patterns, function ($pattern) use ($request) {
            return preg_match("#$pattern#", $request->getPathInfo());
        }));
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
        $this->log_writer->error("Http analyzer failed", ['reason' => $e->getMessage()]);
    }
    
    
}