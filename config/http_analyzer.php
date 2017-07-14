<?php
return [
    // API key to sign requests to the API
    'api_key' => 'your key goes here',
    
    // Enable recording
    'enabled' => env('APIDEVELOPERIO_RECORDING_ENABLED', true),
    
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
        // strip out values of certain query string arguments
        'strip_query_string_values' => ['api_key', 'access_token'],
        // skip any request which match this regular expressions
        // matched against the path part of the URL, like "/api/auth/signup"
        // for example '^/api/auth'
        'skip_url_matching_regexp' => [],
        // Avoid logging this http methods
        'skip_http_methods' => ['OPTIONS', 'HEAD'],
    ],
];