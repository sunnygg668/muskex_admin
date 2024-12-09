<?php

namespace modules\workerman;

use think\facade\Console;

class Workerman
{
    public function AppInit(): void
    {
        if (request()->isCli()) {
            Console::starting(function (\think\Console $console) {
                $console->addCommands([
                    'modules\workerman\command\Worker',
                    'modules\workerman\command\WorkerStartForWin',
                ]);
            });
        }
    }
}