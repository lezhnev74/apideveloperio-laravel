<?php
return [
    // API key to sign requests to the API
    'api_key' => 'your key goes here',
    
    // a directory to put recorded requests at until dumped to the API backend
    'tmp_storage_path' => storage_path('logs/http_analyzer'),
    
    // Configure what data to strip from recorded requests
    'filtering' => [
        // App environment in which recording is off
        'ignore_environment' => ['testing'],
        // array of allowed values:
        // "request_headers", "request_body", "response_headers", "response_body", "log", "external_queries"
        'strip_data' => [],
        // if you still want to see headers, but some of values should be omitted, put hte name of the header here
        // it is case insensitive, for example, good idea is to strip out "Authorization" header's value
        'strip_header_values' => ['authorization'],
    ],
];