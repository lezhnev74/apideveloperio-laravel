<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzer\Laravel;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;

class EventListener
{
    public function onRequestHandled(RequestHandled $event)
    {
        dump('requestHandled event heard');
    }
    
    public function onDatabaseQueryExecuted(QueryExecuted $event)
    {
        dump('queryExecuted event heard');
    }
}