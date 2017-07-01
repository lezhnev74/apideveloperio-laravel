<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzerTest\Backend;

use Carbon\Carbon;
use HttpAnalyzer\Backend\LoggedRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

final class LoggedRequestTest extends TestCase
{
    function test_it_produces_valid_data()
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
}