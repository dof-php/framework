<?php

declare(strict_types=1);

namespace Loy\Framework\Log\Logger;

use Loy\Framework\Kernel;
use Loy\Framework\Log\LoggerInterface;

class File implements LoggerInterface
{
    use LoggerTrait;

    /** @var string: Where Log files will be stored, relative to project root */
    private $directory = ['var', 'log'];

    /** @var string: The dirname for achieving log files */
    private $archive =  'archive';

    /** @var int: The filesize limit of live log file */
    private $filesize = 4194304;    // 4M (default)

    /** @var string: The name of live log file */
    private $live = 'current';

    /** @var string: The suffix of log file */
    private $suffix = 'log';

    /** @var int: Permission of log dirs and files */
    private $permission = 0755;

    /** @var string: Log level keyword */
    private $level;

    /** @var string: Separator of each single log text */
    private $separator = PHP_EOL;

    public function log($level, $message, array $context = [])
    {
        $this->level = stringify($level);
        if (! $this->level) {
            exception('MissingLogLevel');
        }

        // Hard-code an index array to shorten log text
        $this->save(enjson([
            microftime('T Ymd His'),
            stringify($message),
            $context,
        ]));
    }

    public function save(string $log)
    {
        $path = ospath(Kernel::getRoot(), $this->directory);
        $file = ospath($path, join('.', [$this->level, PHP_SAPI, $this->live, $this->suffix]));
        if (! is_dir($path)) {
            mkdir($path, $this->permission, true);
        }

        if (is_file($file) && (filesize($file) >= $this->filesize)) {
            $archive = ospath($path, $this->archive, $this->level);
            if (! is_dir($archive)) {
                mkdir($archive, $this->permission, true);
            }

            $_archive = join('.', [PHP_SAPI, microftime('Ymd-His', '-'), $this->suffix]);
            $_archive = ospath($archive, $_archive);

            rename($file, $_archive);
        }

        file_put_contents($file, $this->seperate($log), FILE_APPEND);
    }

    public function seperate(string $log) : string
    {
        return sprintf("%s%s", $log, $this->separator);
    }

    /**
     * Setter for directory
     *
     * @param array $directory
     * @return File
     */
    public function setDirectory(array $directory)
    {
        if ($dir = trim($directory)) {
            $this->directory = $directory;
        }
    
        return $this;
    }

    /**
     * Setter for live
     *
     * @param string $live
     * @return File
     */
    public function setLive(string $live)
    {
        if ($live = trim($live)) {
            $this->live = $live;
        }
    
        return $this;
    }

    /**
     * Setter for suffix
     *
     * @param string $suffix
     * @return File
     */
    public function setSuffix(string $suffix)
    {
        if ($suffix = trim($suffix)) {
            $this->suffix = $suffix;
        }
    
        return $this;
    }

    /**
     * Setter for archive
     *
     * @param string $archive
     * @return File
     */
    public function setArchive(string $archive)
    {
        if ($archive = trim($archive)) {
            $this->archive = $archive;
        }
    
        return $this;
    }

    /**
     * Setter for filesize
     *
     * @param int $filesize
     * @return File
     */
    public function setFilesize(int $filesize)
    {
        if ($filesize > 0) {
            $this->filesize = $filesize;
        }
    
        return $this;
    }

    /**
     * Setter for separator
     *
     * @param string $separator
     * @return File
     */
    public function setSeparator(string $separator)
    {
        $this->separator = $separator;
    
        return $this;
    }
}