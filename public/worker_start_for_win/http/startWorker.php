<?php

namespace think;

require_once __DIR__ . '/../../../vendor/autoload.php';

(new App())->console->call('WorkerStartForWin', ['http/startWorker']);