<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 25/01/2018
 */

namespace Apideveloper\Laravel\Laravel\Text;

use PHPUnit\Framework\TestCase;

class ExceptionFormatterTest extends TestCase
{
    function test_it_transforms_exception()
    {
        $p     = new \DomainException("message_prev", 200);
        $e     = new \Exception("message", 100, $p);
        $array = ExceptionFormatter::fromException($e)->toArray();


        $this->assertCount(2, $array);
        $this->assertEquals($array[0]['message'], $e->getMessage());
        $this->assertEquals($array[0]['code'], $e->getCode());
        $this->assertEquals($array[0]['class'], get_class($e));
        $this->assertEquals($array[1]['message'], $p->getMessage());
        $this->assertEquals($array[1]['code'], $p->getCode());
        $this->assertEquals($array[1]['class'], get_class($p));
    }
}
