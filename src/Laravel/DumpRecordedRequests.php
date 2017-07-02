<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace HttpAnalyzer\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Logging\Log;

final class DumpRecordedRequests extends Command
{
    protected $signature   = 'http_analyzer:dump';
    protected $description = 'Send recorded http requests to API backend and them remove them from local filesystem';
    /** @var  Repository */
    private $config_repo;
    /** @var  Log */
    private $log;
    /** @var  GuzzleHttpClient */
    private $guzzle_http_client;
    
    
    public function __construct(Repository $config_repo, Log $log, GuzzleHttpClient $client)
    {
        $this->config_repo        = $config_repo;
        $this->log                = $log;
        $this->guzzle_http_client = $client;
        
        parent::__construct();
    }
    
    public function handle()
    {
        $tmp_storage_path = $this->config_repo->get('http_analyzer.tmp_storage_path');
        $dump_file        = $tmp_storage_path . "/recorded_requests";
        
        if (file_exists($dump_file)) {
            $this->renameFile($dump_file);
        }
        
        $dump_files_full_paths = $this->findDumpFiles($tmp_storage_path);
        
        if (count($dump_files_full_paths) && $this->sendDumps($dump_files_full_paths)) {
            array_map('unlink', $dump_files_full_paths);
        }
        
    }
    
    /**
     * Rename the file so no-one will attempt to write in it while transmitting
     *
     * @return string
     */
    protected function renameFile($file)
    {
        $new_name = $file . "_prepared_for_uploading_at_" . date('d-m-Y_H_i_s');
        rename($file, $new_name);
        
        return $new_name;
    }
    
    /**
     * Will scan directory for any files prepared to be dumped to API backend
     * There could be more than just one file, because some dump requests can fail
     * and file will stay untill dumped again next time
     *
     * @param $dump_directory
     *
     * @return array
     */
    protected function findDumpFiles($dump_directory)
    {
        return array_map(function ($filename) use ($dump_directory) {
            return $dump_directory . "/" . $filename;
        }, array_filter(scandir($dump_directory), function ($filename) {
            return strpos($filename, "recorded_requests_prepared_for_uploading_at") === 0;
        }));
    }
    
    /**
     * Send file with recorded requests to API backend server
     *
     *
     * @param array $dump_file
     *
     * @return bool
     */
    protected function sendDumps($dump_files)
    {
        // Files contain valid JSON entries, concatenated with commas,
        // so I just wrap them into an array
        $concatenated_jsons = implode("", array_map('file_get_contents', $dump_files));
        $concatenated_jsons = trim($concatenated_jsons, ",");// remove trailing commas
        $json_data          = '{"requests":[' . $concatenated_jsons . ']}';
        
        $response = $this->guzzle_http_client->request(
            'POST',
            '/api/report/log',
            [
                'headers' => [
                    'content-type' => 'application/json',
                ],
                'body' => $json_data,
            ]
        );
        
        if ($response->getStatusCode() != 200) {
            $this->log->alert("Http Analyzer's backend server could not handle the request", [
                'response_code' => $response->getStatusCode(),
                'response_content' => $response->getBody()->getContents(),
            ]);
        }
        
        return $response->getStatusCode() == 200;
    }
    
}