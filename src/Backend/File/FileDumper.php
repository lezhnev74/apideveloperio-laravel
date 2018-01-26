<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 25/01/2018
 */

namespace Apideveloper\Laravel\Backend\File;


class FileDumper implements LogsDumper
{
    /** @var PersistingStrategy */
    private $options;

    /**
     * FileDumper constructor.
     * @param PersistingStrategy $options
     */
    public function __construct(PersistingStrategy $options)
    {
        $this->options = $options;
    }


    public function dump($data_array)
    {
        $file_path = $this->getFileForDumping();
        file_put_contents(
            $file_path,
            json_encode($data_array) . ",", // comma is for latter wrapping to json array "[...]"
            FILE_APPEND | LOCK_EX
        );
    }


    /**
     * Make a filename for storing buffered log entries
     *
     * @throws \Exception
     * @return string
     */
    protected function getFileForDumping()
    {
        $tmp_path_folder = $this->options->getFolder();
        //
        // Now persist data till the next data dump to the API backend
        //
        if (!is_dir($tmp_path_folder) && !mkdir($tmp_path_folder, 0777, true)) {
            throw new \Exception("Unable to create a directory for storing buffered texts at " . $tmp_path_folder);
        }

        //
        // If there are too many dumped un-sent files then stop recording
        //
        $max_files_count = $this->options->getMaxFiles();
        $dump_files      = array_filter(scandir($tmp_path_folder), function ($file) {
            return strpos($file, "buffered_text_logs") !== false;
        });
        if (count($dump_files) >= $max_files_count) {
            throw new \Exception("Maximum count of dump files reached. Recording of text logs stopped.");
        }

        //
        // make a file to dump every request to
        //
        $file_path     = $tmp_path_folder . "/" . $this->options->getFilenamePrefix();
        $max_file_size = $this->options->getMaxFileSize();
        if (file_exists($file_path) && filesize($file_path) > $max_file_size) {
            // rename it and write to a fresh one
            rename($file_path, $file_path . "_batch_" . date("d-m-Y_H_i_s") . "_" . str_random(8));
        }

        return $file_path;
    }
}