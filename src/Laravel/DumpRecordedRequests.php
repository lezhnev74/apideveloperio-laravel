<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace HttpAnalyzer\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;

final class DumpRecordedRequests extends Command
{
    protected $signature   = 'http_analyzer:dump';
    protected $description = 'Send recorded http requests to API backend and them remove them from local filesystem';
    /** @var  Repository */
    private $config_repo;
    /** @var  LoggerInterface */
    private $log;
    /** @var  GuzzleHttpClient */
    private $guzzle_http_client;
    
    
    public function __construct(Repository $config_repo, LoggerInterface $log, GuzzleHttpClient $client)
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
        // send if there are any
        count($dump_files_full_paths) && $this->sendDumps($dump_files_full_paths);
        
    }
    
    /**
     * Rename the file so no-one will attempt to write in it while transmitting
     *
     * @return string
     */
    protected function renameFile($file)
    {
        $new_name = $file . "_batch_" . date('d-m-Y_H_i_s') . "_" . str_random(8);
        rename($file, $new_name);
        
        return $new_name;
    }
    
    /**
     * Will scan directory for any files prepared to be dumped to API backend
     * There could be more than just one file, because some dump requests can fail
     * and file will stay until dumped again next time
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
            return strpos($filename, "batch") !== false;
        }));
    }
    
    /**
     * Send file with recorded requests to API backend server
     *
     *
     * @param array $dump_file
     *
     * @return void
     */
    protected function sendDumps($dump_files)
    {
        //Each file is big enough to be sent, so send one file per request
        foreach ($dump_files as $dump_file) {
            // Files contain valid JSON entries, concatenated with commas,
            // so I just wrap them into an array
            $concatenated_jsons = file_get_contents($dump_file);
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
                
                // Stop sending because it is something wrong with the API server
                // wait till the next cycle
                return;
            } else {
                $this->log->debug('recorded http requests sent to backend',
                    ['dump_file_name' => $dump_file, 'filesize' => filesize($dump_file)]);
                unlink($dump_file);
            }
        }
    }
    
}
