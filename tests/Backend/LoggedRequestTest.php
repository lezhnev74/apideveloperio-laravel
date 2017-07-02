<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzerTest\Backend;

use HttpAnalyzer\Backend\LoggedRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

final class LoggedRequestTest extends TestCase
{
    function test_it_produces_valid_minimum_required_data()
    {
        $request = Request::create(
            'http://example.org/shop/cart.php?num=one&price=10',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '192.167.35.22',
                'SERVER_ADDR' => '127.0.0.1',
                'REQUEST_TIME' => 1497847022,
                'HTTP_USER_AGENT' => 'Mozilla Firefox',
            ]
        );
        
        $response = new Response('', 200);
        
        $logged_request = new LoggedRequest(
            $request,
            $response,
            100,
            null,
            null,
            LoggedRequest::LOG_MODE_SKIP_REQUEST_HEADERS |
            LoggedRequest::LOG_MODE_SKIP_REQUEST_BODY |
            LoggedRequest::LOG_MODE_SKIP_RESPONSE_BODY |
            LoggedRequest::LOG_MODE_SKIP_RESPONSE_HEADERS
        );
        $data           = $logged_request->toArray();
        
        $this->assertEquals(
            [
                "full_url" => "http://example.org/shop/cart.php?num=one&price=10",
                "http_method" => "post",
                "user_ip" => "192.167.35.22",
                "user_agent" => "Mozilla Firefox",
                "ttr_ms" => 100,
                "timestamp" => 1497847022,
                "http_response_code" => 200,
                "server" => [
                    "hostname" => gethostname(),
                    "ip" => "127.0.0.1",
                ],
            ],
            $data
        );
    }
    
    function test_it_produces_valid_full_optional_data()
    {
        //
        // Set environment
        //
        
        $request = Request::create(
            'http://example.org/shop/cart.php?num=one&price=10',
            'POST',
            [],
            [],
            [],
            [
                'SERVER_ADDR' => '127.0.0.1',
                'REMOTE_ADDR' => '192.167.35.22',
                'REQUEST_TIME' => 1497847022,
                'HTTP_USER_AGENT' => 'Mozilla Firefox',
                'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, sdch, br',
                'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ],
            'I know you can see it'
        );
        
        $response = new Response('<a>Hello guys!</a>', 200, [
            'content-type' => 'text/html',
            'date' => 1497847023,
        ]);
        
        
        //
        // Now produce logged packet with data (which can be sent to API backend)
        //
        
        $logged_request = new LoggedRequest(
            $request,
            $response,
            100,
            "line1\nline2\n",
            [
                [
                    "type" => "database",
                    "vendor" => "mysql",
                    "ttr_ms" => 57,
                    "query" => "SELECT * FROM USERS;",
                ],
            ]
        );
        $data           = $logged_request->toArray();
        
        //
        // Assert that packet has expected format
        //
        
        $this->assertEquals(
            [
                "full_url" => "http://example.org/shop/cart.php?num=one&price=10",
                "http_method" => "post",
                "user_ip" => "192.167.35.22",
                "user_agent" => "Mozilla Firefox",
                "ttr_ms" => 100,
                "timestamp" => 1497847022,
                "http_response_code" => 200,
                
                "server" => [
                    "hostname" => gethostname(),
                    "ip" => "127.0.0.1",
                ],
                "http_request_body" => "I know you can see it",
                "http_request_headers" => [
                    [
                        "name" => "host",
                        "value" => "example.org",
                    ],
                    [
                        "name" => "user-agent",
                        "value" => "Mozilla Firefox",
                    ],
                    [
                        "name" => "accept",
                        "value" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
                    ],
                    [
                        "name" => "accept-language",
                        "value" => "en-us,en;q=0.5",
                    ],
                    [
                        "name" => "accept-charset",
                        "value" => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
                    ],
                    [
                        "name" => "accept-encoding",
                        "value" => "gzip, deflate, sdch, br",
                    ],
                    [
                        "name" => "content-type",
                        "value" => "application/x-www-form-urlencoded",
                    ],
                ],
                "http_response_body" => "<a>Hello guys!</a>",
                "http_response_headers" => [
                    [
                        "name" => "content-type",
                        "value" => "text/html",
                    ],
                    [
                        "name" => "date",
                        "value" => "1497847023",
                    ],
                    [
                        "name" => "cache-control",
                        "value" => "no-cache, private",
                    ],
                ],
                "log" => "line1\nline2\n",
                "external_queries" => [
                    [
                        "type" => "database",
                        "vendor" => "mysql",
                        "ttr_ms" => 57,
                        "query" => "SELECT * FROM USERS;",
                    ],
                ],
            ],
            $data
        );
    }
}