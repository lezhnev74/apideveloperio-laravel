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
                'REQUEST_TIME' => 1497847022,
                'HTTP_USER_AGENT' => 'Mozilla Firefox',
            ]
        );
        
        $response = new Response('', 200);
        
        $logged_request = new LoggedRequest($request, $response, 100);
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
        
        $logged_request = new LoggedRequest($request, $response, 100, "line1\nline2\n");
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
                
                "http_request_body" => "I know you can see it",
                "http_request_headers" => [
                    "accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
                    "accept-encoding" => "gzip, deflate, sdch, br",
                    "accept-language" => "en-us,en;q=0.5",
                    "accept-charset" => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
                    "host" => "example.org",
                    "user-agent" => "Mozilla Firefox",
                    "content-type" => "application/x-www-form-urlencoded",
                ],
                "http_response_body" => "<a>Hello guys!</a>",
                "http_response_headers" => [
                    "cache-control" => "no-cache, private",
                    "content-type" => "text/html",
                    "date" => 1497847023,
                ],
                "external_queries" => [
                    [
                        "type" => "database",
                        "vendor" => "mysql",
                        "ttr_ms" => 57,
                        "query" => "SELECT * FROM USERS;",
                    ],
                ],
                "log" => "line1\nline2\n",
            ],
            $data
        );
    }
}