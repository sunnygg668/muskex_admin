<?php

namespace think;

require_once __DIR__ . '/../../vendor/autoload.php';

use app\worker\library\Monitor;

ini_set('display_errors', 'on');
error_reporting(E_ALL);

function open_processes($processFiles)
{
    $cmd            = '"' . PHP_BINARY . '" ' . implode(' ', $processFiles);
    $descriptorSpec = [STDIN, STDOUT, STDOUT];
    $resource       = proc_open($cmd, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
    if (!$resource) {
        exit("Can not execute $cmd\r\n");
    }
    return $resource;
}

$servers = explode(',', $argv[1]);
foreach ($servers as &$server) {
    $server .= '.php';
}

$resource = open_processes($servers);
$monitor  = new Monitor(include __DIR__ . '/../../config/worker_monitor.php');

// 启动文件监听
while (1) {
    sleep(1);
    if ($monitor->checkAllFilesChange()) {
        $status = proc_get_status($resource);
        $pid    = $status['pid'];
        shell_exec("taskkill /F /T /PID $pid");
        proc_close($resource);
        $resource = open_processes($servers);
    }
}