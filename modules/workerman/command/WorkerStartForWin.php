<?php

namespace modules\workerman\command;

use ba\Filesystem;
use Workerman\Worker;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\console\input\Argument;

/**
 * Windows 专用的启动 WorkerMan 服务命令
 * 通过 public 目录内的 bat 文件启动服务
 */
class WorkerStartForWin extends Command
{
    protected function configure(): void
    {
        $this->setName('WorkerStartForWin')
            ->addArgument('server', Argument::REQUIRED, "The server to start.")
            ->setDescription('Worker server');
    }

    protected function execute(Input $input, Output $output): void
    {
        $server = trim($input->getArgument('server'));
        $server = Filesystem::fsFit(root_path() . "modules/workerman/servers/$server.php");

        if (!file_exists($server)) {
            $output->writeln("<error>$server file does not exist.</error>");
        }

        require_once $server;

        Worker::runAll();
    }
}