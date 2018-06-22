<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 24/01/2018
 */

namespace Apideveloper\Laravel\Laravel\Text;

use Apideveloper\Laravel\Backend\File\LogsDumper;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Psr\Log\LoggerInterface;

class EventListener
{
    private $buffer = [];
    /** @var  Repository */
    private $config_repo;
    /** @var  LoggerInterface */
    private $log_writer;
    /** @var LogsDumper */
    private $dumper;

    /**
     * EventListener constructor.
     * @param string $session_id the key of current app execution (session, used to unite log entry within the same session)
     * @param LogsDumper $dumper
     * @param Repository $config_repo
     * @param LoggerInterface $log_writer
     */
    public function __construct($session_id, LogsDumper $dumper, Repository $config_repo, LoggerInterface $log_writer)
    {
        $this->dumper      = $dumper;
        $this->config_repo = $config_repo;
        $this->log_writer  = $log_writer;

        $this->buffer = [
            'meta' => [
                'env' => app()->environment(),
                'session_id' => $session_id,
                'io_channel' => app()->runningInConsole() ? "console" : "http",
            ],
            'messages' => [],
        ];
    }


    function onLog($level, $message, $context)
    {

        if ($this->isRecordingDisabled()) {
            return;
        }

        $entry = [
            'level' => $level,
            'date' => Carbon::now()->toIso8601String(),
        ];

        // Check exception in the context array
        // Laravel's default behaviour is to throw normal error message
        // and put exception in the context under 'exception' key
        // see 'laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php'
        if (
            ($e = array_get($context, 'exception')) instanceof \Exception ||
            ($e = $message) instanceof \Exception
        ) {
            $entry['exception'] = ExceptionFormatter::fromException($e)->toArray();
            unset($context['exception']);
        } else {
            if (is_scalar($message)) {
                $entry['message'] = $message;
            } else {
                $entry['message'] = (array)$message;
            }
        }

        // Transform context to transferable values (scalars and arrays)
        // Context can contain compound values, like objects, so I want to attempt to stringify them
        array_walk_recursive($context, function (&$value, $key) {
            if (is_object($value)) {
                $value = (string)$value;
            }
        });
        $entry['context'] = (array)$context; // context is supposed to be array

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
        fwrite(fopen('php://stderr', 'w'), (string)$e);
    }

    public function flush()
    {
        try {
            // Dump is performed upon application destruction (at the very end)
            if (count($this->buffer['messages'])) {
                // no logs, no writing
                $this->checkDuplicates();
                $this->dumper->dump($this->buffer);
                $this->buffer['messages'] = []; // flush written data
            }
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }

    /**
     * Remove message duplicates. In some cases 3rd-party packages can emit logging events multiple times (laravel-bugsnag for example)
     * And this logger will record such events multiple times which is wrong
     *
     * This option is off by default since it can remove legitimate log entries which happens to be the same
     *
     * @return void
     */
    private function checkDuplicates()
    {
        $duplicateRemovalEnabled = $this
            ->config_repo
            ->get('apideveloperio_logs.textlog.filtering.remove_duplicates', false);

        if ($duplicateRemovalEnabled) {
            for ($cur = 1, $count = count($this->buffer['messages']); $cur < $count; $cur++) {
                $prev = $cur - 1;
                if ($this->buffer['messages'][$prev] === $this->buffer['messages'][$cur]) {
                    unset($this->buffer['messages'][$prev]);
                }
            }

            // reindex
            $this->buffer['messages'] = array_values($this->buffer['messages']);
        }
    }

    // This is an alternative way to initiate dumping to file
    // So far not required since we hooked to app's shutdown sequence in service provider
//    public function __destruct()
//    {
//        $this->flush();
//    }


}