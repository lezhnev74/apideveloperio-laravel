<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace Apideveloper\Laravel\Tests\Backend;

use Apideveloper\Laravel\Backend\LoggedHTTPRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use function GuzzleHttp\Psr7\parse_query;

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

        $logged_request = new LoggedHTTPRequest(
            $request,
            $response,
            100,
            null,
            null,
            ['strip_data' => ["request_headers", "request_body", "response_headers", "response_body"]]
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

        $logged_request = new LoggedHTTPRequest(
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

        // older response class did not add "private"
        // so I just hack it here (no big deal)
        $data['http_response_headers'][2]['value'] = "no-cache, private";

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

    function test_it_stripps_header_values()
    {
        $request = Request::create(
            'http://example.org/shop/cart.php?num=one&price=10',
            'POST',
            [], [], [], [
                'REMOTE_ADDR' => '192.167.35.22',
                'SERVER_ADDR' => '127.0.0.1',
                'REQUEST_TIME' => 1497847022,
                'HTTP_USER_AGENT' => 'Mozilla Firefox',
            ]
        );

        $logged_request = new LoggedHTTPRequest(
            $request,
            new Response('', 200),
            100,
            null,
            null,
            ['strip_header_values' => ["HOST", "cache-CONtrol"]]
        );
        $data           = $logged_request->toArray();

        $this->assertTrue(
            array_search(['name' => 'host', 'value' => "__STRIPPED_VALUE__"], $data['http_request_headers']) !== false
        );
        $this->assertTrue(
            array_search(['name' => 'cache-control', 'value' => "__STRIPPED_VALUE__"],
                $data['http_response_headers']) !== false
        );
    }

    function test_it_stripps_query_string_values()
    {
        $request = Request::create(
            'https://example.org/shop/cart.php?num=one&api_key=secret_value#some',
            'POST',
            [], [], [], [
                'REMOTE_ADDR' => '192.167.35.22',
                'SERVER_ADDR' => '127.0.0.1',
                'REQUEST_TIME' => 1497847022,
                'HTTP_USER_AGENT' => 'Mozilla Firefox',
            ]
        );

        $logged_request = new LoggedHTTPRequest(
            $request,
            new Response('', 200),
            100,
            null,
            null,
            [
                'strip_query_string_values' => ["api_key"],
            ]
        );
        $data           = $logged_request->toArray();
        $info           = parse_url($data['full_url']);
        $query_string   = parse_query($info['query']);

        $this->assertEquals("__STRIPPED_VALUE__", $query_string['api_key']);
    }

    function test_it_converts_header_names_and_values_to_strings()
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
                'HTTP_USER_AGENT' => null,
            ]
        );
        $request->headers->add([1 => 2]);
        $request->headers->add([3 => null]);

        $response = new Response('', 200);

        $logged_request = new LoggedHTTPRequest($request, $response, 100);
        $data           = $logged_request->toArray();

        $this->assertTrue(array_search(['name' => 'user-agent', 'value' => ''],
                $data['http_request_headers'], true) !== false);
        $this->assertTrue(array_search(['name' => '1', 'value' => '2'],
                $data['http_request_headers'], true) !== false);
        $this->assertTrue(array_search(['name' => '3', 'value' => ''],
                $data['http_request_headers'], true) !== false);

    }

}