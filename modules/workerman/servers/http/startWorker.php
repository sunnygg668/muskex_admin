<?php
// +----------------------------------------------------------------------
// | 默认http服务启动文件
// | 面向过程以便更好的兼容 Windows，它一次只能启动一个服务
// +----------------------------------------------------------------------

use Workerman\Worker;

$config = config('worker_http');

/** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
$worker = new Worker($config['option']['protocol'] . '://' . $config['option']['ip'] . ':' . $config['option']['port'], $config['context']);

// 避免pid混乱
$config['option']['pidFile'] .= '_' . $config['option']['port'];

// Worker 参数设定
foreach ($config['option'] as $key => $value) {
    if (in_array($key, ['protocol', 'ip', 'port'])) continue;

    if (in_array($key, ['stdoutFile', 'daemonize', 'pidFile', 'logFile'])) {
        Worker::${$key} = $value;
    } else {
        $worker->$key = $value;
    }
}

if (class_exists($config['eventHandler'])) {
    $eventHandler = new $config['eventHandler']();

    // 设定回调
    foreach ($config['events'] as $event) {
        if (method_exists($eventHandler, $event)) {
            $worker->$event = [$eventHandler, $event];
        }
    }
}