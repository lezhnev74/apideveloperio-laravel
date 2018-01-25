<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 24/01/2018
 */

namespace Apideveloper\Laravel\Laravel\Text;


/**
 * Class ExceptionFormatter transforms exception object to array
 * @package Apideveloper\Laravel\Laravel\Text
 */
class ExceptionFormatter
{
    private $array = [];

    /**
     * ExceptionFormatter constructor.
     * @param \Exception $e
     */
    private function __construct($e)
    {
        do {
            $this->array[] = $this->getLevel($e);
        } while ($e = $e->getPrevious());
    }

    /**
     * @param \Exception $e
     * @return array
     */
    private function getLevel($e)
    {
        return [
            "class" => get_class($e),
            "message" => $e->getMessage(),
            "code" => $e->getCode(),
            "file" => $e->getFile(),
            "line" => $e->getLine(),
            "trace" => $e->getTraceAsString(),
        ];
    }

//    /**
//     * @param \Exception $e
//     * @return array
//     */
//    private function getTrace($e)
//    {
//        return array_map(function ($trace_step) {
//            return [
//                'file' => array_get($trace_step, 'file'),
//                'line' => array_get($trace_step, 'line'),
//                'function' => array_get($trace_step, 'function'),
//                'class' => array_get($trace_step, 'class'),
//                'type' => array_get($trace_step, 'type'),
//                'args' => $this->getTraceArgs(array_get($trace_step, 'args', [])),
//            ];
//        }, $e->getTrace());
//    }
//
//    private function getTraceArgs($args)
//    {
//        dump($args, json_encode($args, JSON_PRETTY_PRINT));
//
//        return $args;
//    }

    /**
     * @param \Exception|\Throwable $e
     * @return self
     */
    static public function fromException($e)
    {
        return new self($e);
    }

    /**
     * @return array
     */
    function toArray()
    {
        return $this->array;
    }
}