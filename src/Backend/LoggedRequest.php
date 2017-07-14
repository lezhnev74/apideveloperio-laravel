<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace HttpAnalyzer\Backend;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class generates data, which conform with our backend API
 */
final class LoggedRequest
{
    
    /** @var  array */
    private $data = [];
    /** @var array */
    private $filtering_config = [];
    
    
    public function __construct(
        Request $request,
        Response $response,
        $time_to_response_ms,
        $log_text = null,
        $external_queries = null,
        $filtering_config = []
    ) {
        $this->filtering_config = $filtering_config;
        
        $this->fillRequestData($request);
        $this->fillResponseData($response);
        $this->fillServerData($request);
        
        // Append other data
        $this->data['ttr_ms'] = $time_to_response_ms;
        // Make sure log is not longer than 30000 bytes
        if ($log_text && $this->dataShouldBeRecorded('log')) {
            $this->data['log'] = substr($log_text, 0, 30000);
        };
        $this->attachExternalQueries($external_queries);
    }
    
    protected function attachExternalQueries($external_queries)
    {
        
        if (is_null($external_queries)) {
            return;
        }
        
        // Make sure that queries log is not longer than 30000 bytes
        // If so, only save up to that capacity
        $this->data['external_queries'] = [];
        
        $final_json_string = "";
        foreach ($external_queries as $query) {
            $query_json = json_encode($query);
            
            if (strlen($final_json_string . "," . $query_json) <= 30000) {
                $final_json_string                .= "," . $query_json;
                $this->data['external_queries'][] = $query;
            } else {
                break;
            }
        }
        
        
    }
    
    protected function fillRequestData(Request $request)
    {
        $url = $request->fullUrl();
        
        //
        // Strip query string values
        //
        $strip_query_agruments = array_get($this->filtering_config, 'strip_query_string_values', []);
        if (count($strip_query_agruments)) {
            // parse query string
            foreach ($strip_query_agruments as $strip_query_key) {
                if ($request->query->has($strip_query_key)) {
                    $request->query->set($strip_query_key, '__STRIPPED_VALUE__');
                }
            }
            
            $url = $request->fullUrlWithQuery($request->query->all());
        }
        
        $this->data['full_url']    = $url;
        $this->data['http_method'] = strtolower($request->method());
        $this->data['user_ip']     = $request->ip();
        $this->data['timestamp']   = $request->server->get('REQUEST_TIME', Carbon::now()->timestamp);
        $this->data['user_agent']  = $request->headers->get('User-Agent', $request->server->get('HTTP_USER_AGENT'));
        
        
        if ($this->dataShouldBeRecorded('request_headers')) {
            $this->data['http_request_headers'] = [];
            foreach ($request->headers->keys() as $name) {
                $this->data['http_request_headers'][] = [
                    'name' => $name,
                    'value' => $this->stripHeaderValue($name) ?
                        "__STRIPPED_VALUE__" :
                        substr((string)$request->headers->get($name), 0, 1024),
                ];
            }
        }
        
        if ($this->dataShouldBeRecorded('request_body')) {
            // TODO stream body type?
            $this->data['http_request_body'] = substr((string)$request->getContent(), 0, 30000);
        }
        
        if (count($request->allFiles())) {
            $this->data['http_request_files'] = [];
            foreach ($request->allFiles() as $key => $files) {
                // One can upload multiple files under the same name avatars[]
                if (!is_array($files)) {
                    $files = [$files];
                }
                /** @var UploadedFile $file */
                foreach ($files as $file) {
                    $this->data['http_request_files'][] = [
                        'filename' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType(),
                        'fieldname' => $key,
                    ];
                }
            }
        }
    }
    
    protected function fillResponseData(Response $response)
    {
        $this->data['http_response_code'] = $response->getStatusCode();
        
        if ($this->dataShouldBeRecorded('response_body')) {
            $this->data['http_response_body'] = substr((string)$response->getContent(), 0, 30000);
        }
        
        if ($this->dataShouldBeRecorded('response_headers')) {
            $this->data['http_response_headers'] = [];
            foreach ($response->headers->keys() as $name) {
                $this->data['http_response_headers'][] = [
                    'name' => $name,
                    'value' => $this->stripHeaderValue($name) ?
                        "__STRIPPED_VALUE__" :
                        substr((string)$response->headers->get($name), 0, 1024),
                ];
            }
        }
    }
    
    protected function fillServerData(Request $request)
    {
        $this->data['server'] = [
            "hostname" => gethostname(),
            "ip" => $request->server->get('SERVER_ADDR'),
        ];
    }
    
    /**
     * Check that given data should go to recorded request log
     *
     *
     * @param $data_name
     *
     * @return bool
     */
    protected function dataShouldBeRecorded($data_name)
    {
        $strip_data = array_get($this->filtering_config, 'strip_data', []);
        
        return !in_array($data_name, $strip_data);
    }
    
    /**
     * Detect if config tell us to strip this header value from the recorded log
     *
     *
     * @param $header_name
     *
     * @return bool
     */
    protected function stripHeaderValue($header_name)
    {
        $strip_headers_value = array_map('strtolower', array_get($this->filtering_config, 'strip_header_values', []));
        
        return in_array(strtolower($header_name), $strip_headers_value);
    }
    
    public function toArray()
    {
        return $this->data;
    }
    
    public function toJson()
    {
        return json_encode($this->data);
    }
    
}