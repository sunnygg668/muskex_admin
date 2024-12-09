<?php

namespace app\worker\controller;

use Throwable;
use app\common\controller\Frontend;

/**
 * 这是一个示例的 Http 服务控制器
 * 您可以以 TP 的目录结构新建控制器，然后以 pathInfo 模式访问控制器中的方法 [应用/控制器/操作]
 */
class HttpExamples extends Frontend
{
    protected array $noNeedLogin = ['index', 'url', 'auth', 'other'];

    /**
     * 虽然我是 WorkerMan Http 服务的控制器，但是我的访问 URL 与 TP 相似（pathInfo）
     * 使用 worker/Http/index 访问本方法
     */
    public function index(): void
    {
        $this->success('worker-http-index');
    }

    /**
     * URL/请求
     */
    public function url(): void
    {
        echo 'pathInfo:' . $this->request->pathinfo();
        echo PHP_EOL;

        echo 'param:';
        print_r($this->request->param());
        echo PHP_EOL;

        echo 'get:';
        print_r($this->request->get());
        echo PHP_EOL;

        echo 'url:' . $this->request->url() . PHP_EOL;
        echo 'host:' . $this->request->host() . PHP_EOL;
        echo 'domain:' . $this->request->domain() . PHP_EOL;
        echo 'method:' . $this->request->method() . PHP_EOL;
        echo 'controller:' . $this->request->controller() . PHP_EOL;
        echo 'action:' . $this->request->action() . PHP_EOL;
        echo 'protocol:' . $this->request->protocol() . PHP_EOL;
        echo 'ip:' . $this->request->ip() . PHP_EOL;
        echo 'root:' . $this->request->root() . PHP_EOL;
        echo 'port:' . $this->request->port() . PHP_EOL;
        echo 'baseUrl:' . $this->request->baseUrl() . PHP_EOL;
        echo 'isCli:' . $this->request->isCli() . PHP_EOL;
    }

    /**
     * 鉴权
     * @throws Throwable
     */
    public function auth(): void
    {
        echo 'isLogin:' . $this->auth->isLogin() . PHP_EOL;

        if (!$this->auth->isLogin()) return;

        echo 'userId:' . $this->auth->id . PHP_EOL;

        echo 'ruleIds:';
        print_r($this->auth->getRuleIds());
        echo PHP_EOL;

        echo 'ruleList:';
        print_r($this->auth->getRuleList());
        echo PHP_EOL;

        echo 'menus:';
        print_r($this->auth->getMenus());
        echo PHP_EOL;
    }

    /**
     * 其他
     */
    public function other(): void
    {
        // 语言包
        echo __('Example language') . PHP_EOL;
        echo __('HTTP example language') . PHP_EOL;
    }
}