<?php
//全局bootstrap事件
date_default_timezone_set('Asia/Shanghai');

$argvArr = $argv;
array_shift($argvArr);
$command = $argvArr[0] ?? null;
if ($command === 'model') {
    \EasySwoole\EasySwoole\Core::getInstance()->initialize();
}
\EasySwoole\Command\CommandManager::getInstance()->addCommand(new \EasySwoole\FastDb\Commands\ModelCommand());