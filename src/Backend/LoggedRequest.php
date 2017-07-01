<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzer\Backend;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * This class generates data, which conform with our backend API
 */
final class LoggedRequest
{
    /** @var  array */
    private $data = [];
    
    
    public function __construct(
        Request $request,
        Response $response,
        $time_to_response_ms,
        $log_text = null
    ) {
        $this->fillRequestData($request);
        $this->fillResponseData($response, $time_to_response_ms, $log_text);
    }
    
    private function fillRequestData(Request $request)
    {
        $this->data['full_url']    = $request->fullUrl();
        $this->data['http_method'] = strtolower($request->method());
        $this->data['user_ip']     = $request->ip();
        $this->data['timestamp']   = $request->server->get('REQUEST_TIME', Carbon::now()->timestamp);
        $this->data['user_agent']  = $request->headers->get('User-Agent', $request->server->get('HTTP_USER_AGENT'));
    }
    
    private function fillResponseData(Response $response, $time_to_response_ms, $log_text)
    {
        $this->data['http_response_code'] = $response->getStatusCode();
        $this->data['ttr_ms']             = $time_to_response_ms;
    }
    
    public function toArray()
    {
        return $this->data;
    }
    
    function toJson()
    {
        return json_encode($this->data);
    }
    
    
}