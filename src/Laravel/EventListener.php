<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzer\Laravel;

use Illuminate\Foundation\Http\Events\RequestHandled;

class EventListener
{
    public function handle(RequestHandled $event)
    {
        dump('event listener called');
    }
}