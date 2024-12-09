<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace app\worker\library;

use SplFileInfo;
use Workerman\Timer;
use Workerman\Worker;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Monitor
{
    /**
     * @var string
     */
    public static string $lockFile = __DIR__ . '/../../../runtime/worker/monitor.lock';

    /**
     * Pause monitor
     * @return void
     */
    public static function pause(): void
    {
        file_put_contents(static::$lockFile, time());
    }

    /**
     * Resume monitor
     * @return void
     */
    public static function resume(): void
    {
        clearstatcache();
        if (is_file(static::$lockFile)) {
            unlink(static::$lockFile);
        }
    }

    /**
     * Whether monitor is paused
     * @return bool
     */
    public static function isPaused(): bool
    {
        clearstatcache();
        return file_exists(static::$lockFile);
    }

    public function __construct(protected array $options = [])
    {
        if (!Worker::getAllWorkers()) return;
        static::resume();

        $disableFunctions = explode(',', ini_get('disable_functions'));
        if (in_array('exec', $disableFunctions, true)) {
            echo "\nMonitor file change turned off because exec() has been disabled by disable_functions setting in " . PHP_CONFIG_FILE_PATH . "/php.ini\n";
        } else {
            if (DIRECTORY_SEPARATOR === '/' && (!Worker::$daemonize || !empty($this->options['switch']))) {
                Timer::add($this->options['interval'], [$this, 'checkAllFilesChange']);
            }
        }

        $memoryLimit = $this->getMemoryLimit($this->options['memory_limit'] ?? null);
        if ($memoryLimit && DIRECTORY_SEPARATOR === '/') {
            Timer::add(60, [$this, 'checkMemory'], [$memoryLimit]);
        }
    }

    /**
     * @return bool
     */
    public function checkAllFilesChange(): bool
    {
        if (static::isPaused()) {
            return false;
        }
        foreach ($this->options['paths'] as $path) {
            if ($this->checkFilesChange($path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $monitorDir
     * @return bool
     */
    public function checkFilesChange($monitorDir): bool
    {
        static $lastMtime, $tooManyFilesCheck;
        if (!$lastMtime) {
            $lastMtime = time();
        }
        clearstatcache();
        if (!is_dir($monitorDir)) {
            if (!is_file($monitorDir)) {
                return false;
            }
            $iterator = [new SplFileInfo($monitorDir)];
        } else {
            // recursive traversal directory
            $dirIterator = new RecursiveDirectoryIterator($monitorDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS);
            $iterator    = new RecursiveIteratorIterator($dirIterator);
        }
        $count = 0;
        foreach ($iterator as $file) {
            $count++;

            if (is_dir($file->getRealPath())) {
                continue;
            }

            // check mtime
            if (in_array($file->getExtension(), $this->options['extensions'], true) && $lastMtime < $file->getMTime()) {
                $var = 0;
                exec('"' . PHP_BINARY . '" -l ' . $file, $out, $var);
                $lastMtime = $file->getMTime();
                if ($var) continue;

                echo "\n[Monitor] $file 已经更新并重新载入\n\n";

                // send SIGUSR1 signal to master process for reload
                if (DIRECTORY_SEPARATOR === '/') {
                    posix_kill(posix_getppid(), SIGUSR1);
                } else {
                    return true;
                }
                break;
            }
        }

        if (!$tooManyFilesCheck && $count > 1000) {
            echo "Monitor: There are too many files ($count files) in $monitorDir which makes file monitoring very slow\n";
            $tooManyFilesCheck = 1;
        }
        return false;
    }

    /**
     * @param $memoryLimit
     * @return void
     */
    public function checkMemory($memoryLimit): void
    {
        if (static::isPaused() || $memoryLimit <= 0) {
            return;
        }
        $ppid         = posix_getppid();
        $childrenFile = "/proc/$ppid/task/$ppid/children";
        if (!is_file($childrenFile) || !($children = file_get_contents($childrenFile))) {
            return;
        }
        foreach (explode(' ', $children) as $pid) {
            $pid        = (int)$pid;
            $statusFile = "/proc/$pid/status";
            if (!is_file($statusFile) || !($status = file_get_contents($statusFile))) {
                continue;
            }
            $mem = 0;
            if (preg_match('/VmRSS\s*?:\s*?(\d+?)\s*?kB/', $status, $match)) {
                $mem = $match[1];
            }
            $mem = (int)($mem / 1024);
            if ($mem >= $memoryLimit) {
                posix_kill($pid, SIGINT);
            }
        }
    }

    /**
     * Get memory limit
     * @param $memoryLimit
     * @return float|int
     */
    protected function getMemoryLimit($memoryLimit): float|int
    {
        if ($memoryLimit === 0) {
            return 0;
        }
        $usePhpIni = false;
        if (!$memoryLimit) {
            $memoryLimit = ini_get('memory_limit');
            $usePhpIni   = true;
        }

        if ($memoryLimit == -1) {
            return 0;
        }
        $unit = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        if ($unit === 'g') {
            $memoryLimit = 1024 * (int)$memoryLimit;
        } else if ($unit === 'm') {
            $memoryLimit = (int)$memoryLimit;
        } else if ($unit === 'k') {
            $memoryLimit = ((int)$memoryLimit / 1024);
        } else {
            $memoryLimit = ((int)$memoryLimit / (1024 * 1024));
        }
        if ($memoryLimit < 30) {
            $memoryLimit = 30;
        }
        if ($usePhpIni) {
            $memoryLimit = (int)(0.8 * $memoryLimit);
        }
        return $memoryLimit;
    }
}