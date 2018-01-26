<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 25/01/2018
 */

namespace Apideveloper\Laravel\Laravel;


use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Log\Writer;

class SendDumpsToDashboard extends Command
{
    protected $signature = 'apideveloper:send-logs {--types=http,text : Specify what logs to dump}';
    protected $description = 'Send recorded http requests and text logs to API backend and them remove them from local filesystem';
    /** @var Repository */
    private $config_repo;
    /** @var GuzzleHttpClient */
    private $guzzle;
    /** @var Writer */
    private $log;

    /**
     * SendDumpsToDashboard constructor.
     * @param Repository $config_repo
     * @param GuzzleHttpClient $guzzle
     * @param Writer $log
     */
    public function __construct(Repository $config_repo, GuzzleHttpClient $guzzle, Writer $log)
    {
        $this->config_repo = $config_repo;
        $this->guzzle      = $guzzle;
        $this->log         = $log;

        parent::__construct();
    }


    public function handle()
    {
        $types = explode(",", $this->option('types'));

        in_array('http', $types) && $this->sendHTTPLogs();
        in_array('text', $types) && $this->sendTextLogs();
    }

    function sendHTTPLogs()
    {
        $enabled = $this->config_repo->get('apideveloperio_logs.httplog.enabled', false);
        if (!$enabled) {
            // this featured is disabled, cancel operation
            return;
        }

        $url         = '/api/report/log';
        $batch_key   = "requests";
        $api_key     = $this->config_repo->get('apideveloperio_logs.httplog.api_key');
        $file_prefix = $this->config_repo->get('apideveloperio_logs.httplog.dump_file_prefix', 'recorded_requests');
        $folder      = $this->config_repo->get('apideveloperio_logs.httplog.tmp_storage_path', 'unknown_path');


        $dump_files_full_paths = $this->findDumpFiles($folder, $file_prefix);

        count($dump_files_full_paths) && $this->sendDumps($url, $api_key, $dump_files_full_paths, $batch_key);
    }

    function sendTextLogs()
    {
        $enabled = $this->config_repo->get('apideveloperio_logs.textlog.enabled', false);
        if (!$enabled) {
            // this featured is disabled, cancel operation
            return;
        }

        $url         = '/api/report/log-text';
        $batch_key   = "entries";
        $api_key     = $this->config_repo->get('apideveloperio_logs.textlog.api_key');
        $file_prefix = $this->config_repo->get('apideveloperio_logs.textlog.dump_file_prefix', 'buffered_text_logs');
        $folder      = $this->config_repo->get('apideveloperio_logs.textlog.tmp_storage_path', 'unknown_path');


        $dump_files_full_paths = $this->findDumpFiles($folder, $file_prefix);
        count($dump_files_full_paths) && $this->sendDumps($url, $api_key, $dump_files_full_paths, $batch_key);
    }

    /**
     * Will scan directory for any files prepared to be dumped to API backend
     * There could be more than just one file, because some dump requests can fail
     * and file will stay until dumped again next time
     *
     * @param $dump_directory
     * @param $file_prefix
     *
     * @return array
     */
    protected function findDumpFiles($dump_directory, $file_prefix)
    {
        if (!is_dir($dump_directory) || !is_readable($dump_directory)) {
            // directory where logs are supposed to be stored is not discoverable
            // just skip it and continue
            return [];
        }

        // Before finding files, I want to rename last file and thus mark it for sending
        $current_file = $dump_directory . "/" . $file_prefix;
        if (is_file($current_file) && filesize($current_file)) {
            rename($current_file, $current_file . "_batch_" . date("d-m-Y_H_i_s") . "_" . str_random(8));
        }

        // Now we are ready to look for files to send
        return array_map(function ($filename) use ($dump_directory) {
            return $dump_directory . "/" . $filename;
        }, array_filter(scandir($dump_directory), function ($filename) use ($file_prefix) {
            return strpos($filename, "batch") !== false && strpos($filename, $file_prefix) !== false;
        }));
    }

    /**
     * Send file with recorded requests to API backend server
     *
     * @param $url
     * @param $api_key
     * @param $dump_files
     * @param $batch_key
     */
    protected function sendDumps($url, $api_key, $dump_files, $batch_key)
    {
        //Each file is big enough to be sent, so send one file per request
        foreach ($dump_files as $dump_file) {
            // Files contain valid JSON entries, concatenated with commas,
            // so I just wrap them into an array
            $concatenated_jsons = file_get_contents($dump_file);
            $concatenated_jsons = trim($concatenated_jsons, ",");// remove trailing commas
            $json_data          = '{"' . $batch_key . '":[' . $concatenated_jsons . ']}';

            $response = $this->guzzle->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'content-type' => 'application/json',
                    ],
                    'query' => [
                        'api_key' => $api_key,
                    ],
                    'body' => $json_data,
                ]
            );

            if ($response->getStatusCode() != 200) {
                $this->log->alert("Apideveloper's backend server could not handle the request", [
                    'response_code' => $response->getStatusCode(),
                    'response_content' => $response->getBody()->getContents(),
                ]);

                // Stop sending because it is something wrong with the API server
                // wait till the next cycle
                return;
            } else {
                $this->log->debug('recorded logs sent to apideveloper.io dashboard',
                    ['dump_file_name' => $dump_file, 'filesize' => filesize($dump_file)]);
                unlink($dump_file);
            }
        }
    }
}