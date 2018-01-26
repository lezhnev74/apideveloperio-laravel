<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 24/01/2018
 */

namespace Apideveloper\Laravel\Laravel\Text;

use Apideveloper\Laravel\Backend\File\LogsDumper;
use Illuminate\Config\Repository;
use Illuminate\Log\Writer;

class EventListener
{
    private $buffer = [];
    /** @var  Repository */
    private $config_repo;
    /** @var  Writer */
    private $log_writer;
    /** @var LogsDumper */
    private $dumper;

    /**
     * EventListener constructor.
     * @param string $app_execution_id the key of current app execution
     * @param LogsDumper $dumper
     * @param Repository $config_repo
     * @param Writer $log_writer
     */
    public function __construct($app_execution_id, LogsDumper $dumper, Repository $config_repo, Writer $log_writer)
    {
        $this->dumper      = $dumper;
        $this->config_repo = $config_repo;
        $this->log_writer  = $log_writer;

        $this->buffer = [
            'meta' => [
                'env' => app()->environment(),
                'requestId' => $app_execution_id // link to handled http request if any
            ],
            'messages' => [],
        ];
    }


    function onLog($level, $message, $context)
    {

        if ($this->isRecordingDisabled()) {
            return;
        }


        // Context can contain compound values, like objects, so I want to attempt to stringify them
        array_walk_recursive($context, function (&$value, $key) {
            if (is_object($value)) {
                $value = (string)$value;
            }
        });

        $entry = [
            'level' => $level,
            'context' => (array)$context, // context is supposed to be array
        ];

        if ($message instanceof \Exception) {
            $entry['exception'] = ExceptionFormatter::fromException($message)->toArray();
        } else {
            if (is_scalar($message)) {
                $entry['message'] = $message;
            } else {
                $entry['message'] = (array)$message;
            }
        }

        // Ok push the log to the buffer until dumped to the file
        $this->buffer['messages'][] = $entry;
    }

    /**
     * Detect if current environment is allowed to be recorded
     *
     *
     * @return bool
     */
    protected function isRecordingDisabled()
    {
        return
            in_array(
                app()->environment(),
                $this->config_repo->get('apideveloperio_logs.textlog.filtering.ignore_environment', [])
            ) || !$this->config_repo->get('apideveloperio_logs.textlog.enabled');
    }


    /**
     * Silently fail with log message
     *
     *
     * @param \Throwable $e
     *
     * @return void
     */
    protected function fail(\Throwable $e)
    {
        // do nothing since there is an issue with logging
        // the only thing we can do is throw something to the stderr
        fwrite(STDERR, (string)$e);
    }

    public function flush()
    {
        try {
            // Dump is performed upon application destruction (at the very end)
            if (count($this->buffer['messages'])) {
                // no logs, no writing
                $this->dumper->dump($this->buffer);
                $this->buffer['messages'] = []; // flush written data
            }
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }

    // This is an alternative way to initiate dumping to file
    // So far not required since we hooked to app's shutdown sequence in service provider
//    public function __destruct()
//    {
//        $this->flush();
//    }


}