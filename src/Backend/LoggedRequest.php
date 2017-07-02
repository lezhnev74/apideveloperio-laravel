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
    const LOG_MODE_ALL                   = 0;
    const LOG_MODE_SKIP_REQUEST_HEADERS  = 1;
    const LOG_MODE_SKIP_REQUEST_BODY     = 2;
    const LOG_MODE_SKIP_RESPONSE_HEADERS = 4;
    const LOG_MODE_SKIP_RESPONSE_BODY    = 8;
    
    
    /** @var  array */
    private $data = [];
    /** @var  int */
    private $mode;
    
    
    public function __construct(
        Request $request,
        Response $response,
        $time_to_response_ms,
        $log_text = null,
        $external_queries = null,
        $mode = self::LOG_MODE_ALL
    ) {
        $this->mode = $mode;
        
        $this->fillRequestData($request);
        $this->fillResponseData($response);
        $this->fillServerData($request);
        
        // Append other data
        $this->data['ttr_ms'] = $time_to_response_ms;
        if ($log_text) {
            $this->data['log'] = $log_text;
        };
        if (is_array($external_queries) && count($external_queries)) {
            $this->data['external_queries'] = $external_queries;
        }
    }
    
    private function fillRequestData(Request $request)
    {
        $this->data['full_url']    = $request->fullUrl();
        $this->data['http_method'] = strtolower($request->method());
        $this->data['user_ip']     = $request->ip();
        $this->data['timestamp']   = $request->server->get('REQUEST_TIME', Carbon::now()->timestamp);
        $this->data['user_agent']  = $request->headers->get('User-Agent', $request->server->get('HTTP_USER_AGENT'));
        
        
        if (!($this->mode & self::LOG_MODE_SKIP_REQUEST_HEADERS)) {
            $this->data['http_request_headers'] = [];
            foreach ($request->headers->keys() as $name) {
                $this->data['http_request_headers'][$name] = $request->headers->get($name);
            }
        }
        
        if (!($this->mode & self::LOG_MODE_SKIP_REQUEST_BODY)) {
            // TODO stream body type?
            $this->data['http_request_body'] = $request->getContent();
        }
        
        if (count($request->allFiles())) {
            $this->data['http_request_files'] = [];
            foreach ($request->allFiles() as $file) {
                $this->data['http_request_files'][] = [
                    'name' => $file['name'],
                    'size' => $file['size'],
                    'mime' => $file['type'],
                ];
            }
        }
    }
    
    private function fillResponseData(Response $response)
    {
        $this->data['http_response_code'] = $response->getStatusCode();
        
        if (!($this->mode & self::LOG_MODE_SKIP_RESPONSE_BODY)) {
            $this->data['http_response_body'] = $response->getContent();
        }
        
        if (!($this->mode & self::LOG_MODE_SKIP_RESPONSE_HEADERS)) {
            $this->data['http_response_headers'] = [];
            foreach ($response->headers->keys() as $name) {
                $this->data['http_response_headers'][$name] = $response->headers->get($name);
            }
        }
    }
    
    private function fillServerData(Request $request)
    {
        $this->data['server'] = [
            "hostname" => gethostname(),
            "ip" => $request->server->get('SERVER_ADDR'),
        ];
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