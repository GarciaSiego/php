<?php
/**
 * Created by PhpStorm.
 * User: Garcia
 */

namespace General;


use DateTime;
use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class CustomRotatingFileHandler
 * Based on RotatingFileHandler, but handles more then one day.
 *
 * @package General
 */
class CustomRotatingFileHandler extends StreamHandler
{
    public const FILE_PER_DAY = 'Y-m-d';
    public const FILE_PER_MONTH = 'Y-m';
    public const FILE_PER_YEAR = 'Y';

    protected $filename;
    protected $folderName;
    protected $mustRotate;
    protected $rotationTime;
    protected $filenameFormat;
    protected $dateFormat;
    protected $oldFiles;

    /**
     * @param string   $filename
     * @param string   $folder
     * @param int      $level
     * @param bool     $bubble
     * @param int|null $filePermission
     * @param bool     $useLocking
     * @param string   $time
     * @throws \Exception
     */
    public function __construct(string $filename, $folder = "", $level = Logger::DEBUG, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false, $time = "-1 day -1 week")
    {
        $this->filename = $filename;
        $this->folderName = $folder;
        $this->rotationTime = new \DateTimeImmutable($time);
        $this->filenameFormat = '{date}';
        $this->dateFormat = static::FILE_PER_DAY;

        parent::__construct($this->getTimedFilename(), $level, $bubble, $filePermission, $useLocking);
    }

    protected function getTimedFilename(): string
    {
        $fileInfo = pathinfo($this->getFullPath());
        $timedFilename = str_replace(
            ['{date}'],
            [date($this->dateFormat)],
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );

        if (!empty($fileInfo['extension'])) {
            $timedFilename .= '.' . $fileInfo['extension'];
        }

        return $timedFilename;
    }

    /**
     * Returns the full path
     *
     * @return string
     */
    private function getFullPath(): string
    {
        return $this->folderName . DIRECTORY_SEPARATOR . $this->filename;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        parent::reset();

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    /**
     * Rotates the files.
     */
    protected function rotate(): void
    {
        foreach ($this->oldFiles as $file) {
            $filePath = $this->folderName . DIRECTORY_SEPARATOR . $file;
            if (is_writable($filePath)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
                    return false;
                });
                unlink($filePath);
                restore_error_handler();
            }
        }

        $this->mustRotate = false;
    }

    public function setFilenameFormat(string $filenameFormat, string $dateFormat): self
    {
        if (!preg_match('{^Y(([/_.-]?m)([/_.-]?d)?)?$}', $dateFormat)) {
            throw new InvalidArgumentException(
                'Invalid date format - format must be one of ' .
                'RotatingFileHandler::FILE_PER_DAY ("Y-m-d"), RotatingFileHandler::FILE_PER_MONTH ("Y-m") ' .
                'or RotatingFileHandler::FILE_PER_YEAR ("Y"), or you can set one of the ' .
                'date formats using slashes, underscores and/or dots instead of dashes.'
            );
        }
        if (substr_count($filenameFormat, '{date}') === 0) {
            throw new InvalidArgumentException(
                'Invalid filename format - format must contain at least `{date}`, because otherwise rotating is impossible.'
            );
        }
        $this->filenameFormat = $filenameFormat;
        $this->dateFormat = $dateFormat;
        $this->url = $this->getTimedFilename();
        $this->close();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        parent::close();

        if (true === $this->mustRotate) {
            $this->rotate();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        // on the first record written, if the log is new, we should rotate
        if (null === $this->mustRotate) {
            $this->mustRotate = !file_exists($this->url);
        }
        $this->getOlderFiles();
        if (count($this->oldFiles) > 0) {
            $this->mustRotate = true;
            $this->close();
        }

        parent::write($record);
    }

    /**
     * Returns array of files older then rotate time
     */
    private function getOlderFiles()
    {
        $files = [];
        $logFiles = glob($this->getGlobPattern());
        if (is_array($logFiles)) {
            foreach ($logFiles as $log) {
                $fileInfo = pathinfo($log);
                $logDate = new DateTime($fileInfo["filename"]); // Suppress possible errors, meaning file read that dosn't belong there.
                if ($logDate < $this->rotationTime) {
                    $files[] = $fileInfo["basename"];
                }
            }
        }
        $this->oldFiles = $files;
    }

    protected function getGlobPattern(): string
    {
        $fileInfo = pathinfo($this->getFullPath());
        $glob = str_replace(
            ['{date}'],
            ['[0-9][0-9][0-9][0-9]*'],
            $fileInfo['dirname'] . '/' . $this->filenameFormat
        );
        if (!empty($fileInfo['extension'])) {
            $glob .= '.' . $fileInfo['extension'];
        }

        return $glob;
    }
}