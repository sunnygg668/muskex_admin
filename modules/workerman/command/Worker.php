<?php

namespace modules\workerman\command;

use ba\Filesystem;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\console\input\Option;
use think\console\input\Argument;
use Workerman\Worker as WorkerManWorker;

/**
 * 模块内置的Worker命令
 */
class Worker extends Command
{
    protected function configure(): void
    {
        $this->setName('worker')
            ->addArgument('server', Argument::REQUIRED, "The server to start.")
            ->addArgument('action', Argument::REQUIRED, "start|stop|restart|reload|status")
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Start in daemon mode.')
            ->setDescription('Worker server');
    }

    protected function execute(Input $input, Output $output): void
    {
        $action = trim($input->getArgument('action'));
        $server = trim($input->getArgument('server'));

        if (!in_array($action, ['start', 'stop', 'restart', 'reload', 'status'])) {
            $output->writeln("<error>Invalid argument action:$action, Expected start|stop|restart|reload|status .</error>");
            exit(1);
        }

        if (str_starts_with(strtolower(PHP_OS), 'win') || DIRECTORY_SEPARATOR === '\\') {
            $output->writeln("<error>Windows does not support command startup. Please Double-click the public/worker_start_for_win/*.bat file to run it.</error>");
            exit(1);
        }

        // 检查扩展
        if (!extension_loaded('pcntl')) {
            $output->writeln("<error>Please install pcntl extension. See https://doc.workerman.net/appendices/install-extension.html</error>");
            exit(1);
        }
        if (!extension_loaded('posix')) {
            $output->writeln("<error>Please install posix extension. See https://doc.workerman.net/appendices/install-extension.html</error>");
            exit(1);
        }

        global $argv;
        array_shift($argv);
        array_shift($argv);
        array_unshift($argv, 'Worker');

        $serverPath = Filesystem::fsFit(root_path() . "modules/workerman/servers/$server/start*.php");
        $startFiles = glob($serverPath);
        if (!$startFiles) {
            $output->writeln("<error>$server server does not exist.</error>");
            exit(1);
        }
        foreach (glob($serverPath) as $startFile) {
            require_once $startFile;
        }
        WorkerManWorker::runAll();
    }
}
