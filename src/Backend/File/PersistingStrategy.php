<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 25/01/2018
 */

namespace Apideveloper\Laravel\Backend\File;


class PersistingStrategy
{
    /** @var string */
    private $folder;
    /** @var int */
    private $max_files;
    /** @var int */
    private $max_file_size;
    /** @var string */
    private $filename_prefix;

    /**
     * PersistingOptions constructor.
     * @param string $folder
     * @param int $max_files
     * @param int $max_file_size
     * @param string $filename_prefix
     */
    public function __construct($folder, $max_files, $max_file_size_bytes, $filename_prefix)
    {
        $max_files           = intval($max_files);
        $max_file_size_bytes = intval($max_file_size_bytes);

        if ($max_files < 1) {
            throw new \InvalidArgumentException("Max files count has wrong value: $max_files");
        }
        if ($max_file_size_bytes < 1) {
            throw new \InvalidArgumentException("Max file size must positive, but set as: $max_file_size_bytes");
        }

        $this->folder          = $folder;
        $this->max_files       = $max_files;
        $this->max_file_size   = $max_file_size_bytes;
        $this->filename_prefix = $filename_prefix;
    }

    /**
     * @return string
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @return int
     */
    public function getMaxFiles()
    {
        return $this->max_files;
    }

    /**
     * @return int
     */
    public function getMaxFileSize()
    {
        return $this->max_file_size;
    }

    /**
     * @return string
     */
    public function getFilenamePrefix()
    {
        return $this->filename_prefix;
    }


}