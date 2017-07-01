<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzer\Laravel;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;

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
    
    public function onRequestHandled(RequestHandled $event)
    {
        dump('requestHandled event heard');
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
}