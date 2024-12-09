<?php

namespace app\worker\controller;

use Throwable;
use think\App;
use GatewayWorker\Lib\Gateway;
use app\common\library\Auth as UserAuth;
use app\admin\library\Auth as AdminAuth;
use app\worker\library\WorkerWsApp;

/**
 * 这是一个示例的 WebSocket 服务控制器
 * 您可以以 TP 的目录结构新建控制器，然后前端使用 websocket.send('{"pathInfo":"worker/websocket/index"}') 的方式访问控制器中的方法 [应用/控制器/操作]
 */
class WebSocketExamples
{
    /**
     * 构造方法
     * @param WorkerWsApp $app 应用对象
     * @throws Throwable
     */
    public function __construct(protected App $app)
    {
    }

    /**
     * 前端访问本方法：websocket.send('{"pathInfo":"worker/WebSocketExamples/index"}')
     */
    public function index(): void
    {
        $this->app->send('example-index', 'WebSocket Examples Index');
    }

    /**
     * 发送消息示例
     * 消息发送后程序并不会结束运行，您可以手动 return，请勿 exit 或 die
     * 前端访问本方法：websocket.send('{"pathInfo":"worker/WebSocketExamples/message"}')
     */
    public function message(): void
    {
        // 发送消息示例
        $this->app->send('example-message', [
            'welcome'  => '欢迎使用 WebSocket 服务~',
            'clientId' => $this->app->clientId,
            'message'  => $this->app->message,// 收到的消息
            'get'      => $this->app->request->get(),// 连接 ws 时的参数
            'server'   => $this->app->request->server(),
        ]);
    }

    /**
     * 验权示例
     * @throws Throwable
     */
    public function auth(): void
    {
        /**
         * 获取会员的 Auth 实例
         * 示例从 GET 数据中获取 token，该 token 需要前端开发者在连接 ws 时传递
         * 您也可以使用其他方式传递 token，比如 $this->app->message['ba_user_token']
         * 获取 Auth 实例其实就是拿到 token，并传递给 Auth->init() 方法即可
         */
        $userAuth = UserAuth::instance();
        $userAuth->init($this->app->request->get('ba_user_token', ''));
        // var_dump($userAuth->isLogin());


        /**
         * 获取管理员 Auth 实例与会员类似，只是 Auth 类不一样
         */
        $adminAuth = AdminAuth::instance();
        $adminAuth->init($this->app->request->get('ba_token', ''));
        // var_dump($adminAuth->isLogin());


        /**
         * 绑定 uid 示例
         * 我们建议您绑定 uid 时总是加上自定义的后缀，特别是开发模块时
         */
        if ($userAuth->isLogin()) {
            $userId = $userAuth->id . '|custom_suffix';
            Gateway::bindUid($this->app->clientId, $userId);

            $this->app->send('auth', [
                'clientId' => $this->app->clientId,
            ], $userId);
        }

        // Gateway::sendToUid($userId, '测试消息');
        // Gateway::isUidOnline($userId);
    }
}