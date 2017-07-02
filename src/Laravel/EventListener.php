<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzer\Laravel;

use HttpAnalyzer\Backend\LoggedRequest;
use Illuminate\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;

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
    
    /**
     * EventListener constructor.
     *
     * @param Repository $config_repo
     */
    public function __construct(Repository $config_repo) { $this->config_repo = $config_repo; }
    
    
    public function onRequestHandled(RequestHandled $event)
    {
        
        // If possible - calculate time duration
        $time_to_response = defined('LARAVEL_START') ?
            intval((microtime(true) - constant('LARAVEL_START')) * 1000) :
            1;
        
        //
        // Prepare packet with all recorded data
        //
        
        $logged_request = new LoggedRequest(
            $event->request,
            $event->response,
            $time_to_response,
            implode("\n", $this->recorded_data['log_entries']),
            $this->recorded_data['external_queries'],
            LoggedRequest::LOG_MODE_ALL
        );
        
        $tmp_storage_path = $this->config_repo->get('http_analyzer.tmp_storage_path');
        $this->saveRecordedRequest($logged_request, $tmp_storage_path);
    }
    
    public function onDatabaseQueryExecuted(QueryExecuted $event)
    {
        
        $pdo    = $event->connection->getPdo();
        $vendor = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) .
                  "/" .
                  $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        
        // Log this query
        $this->recorded_data['external_queries'][] = [
            "query" => $event->sql,
            "ttr_ms" => intval($event->time * 1000),
            "type" => "database",
            "vendor" => $vendor,
        ];
    }
    
    public function onLog(MessageLogged $event)
    {
        $this->recorded_data['log_entries'][] = $event->message .
                                                " context: " .
                                                serialize($event->context);
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
}