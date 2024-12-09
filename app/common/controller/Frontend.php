<?php

namespace app\common\controller;

use app\common\library\RedisKey;
use app\custom\library\RedisUtil;
use Throwable;
use think\facade\Event;
use app\common\library\Auth;
use think\exception\HttpResponseException;
use app\common\library\token\TokenExpirationException;

class Frontend extends Api
{
    /**
     * 无需登录的方法
     * 访问本控制器的此方法，无需会员登录
     * @var array
     */
    protected array $noNeedLogin = [];

    /**
     * 无需鉴权的方法
     * @var array
     */
    protected array $noNeedPermission = [];

    /**
     * 权限类实例
     * @var Auth
     */
    protected Auth $auth;

    /**
     * 初始化
     * @throws Throwable
     * @throws HttpResponseException
     */
    public function initialize(): void
    {
        parent::initialize();

        $needLogin = !action_in_arr($this->noNeedLogin);

        $token = '';
        try {

            // 初始化会员鉴权实例
            $this->auth = Auth::instance();
            $token      = get_auth_token(['ba', 'user', 'token']);
            if ($token) $this->auth->init($token);

        } catch (TokenExpirationException) {
            if ($needLogin) {
                $this->error(__('Token expiration'), [], 409);
            }
        }

        if ($needLogin) {
            if (!$this->auth->isLogin()) {
                $this->error(__('Please login first'), [
                    'type' => $this->auth::NEED_LOGIN,
                    'token' => $token
                ], $this->auth::LOGIN_RESPONSE_CODE);
            }
            if (!action_in_arr($this->noNeedPermission)) {
                $routePath = ($this->app->request->controllerPath ?? '') . '/' . $this->request->action(true);
                if (!$this->auth->check($routePath)) {
                    $this->error(__('You have no permission'), [], 401);
                }
            }

            $this->userLimit();
        }

        // 会员验权和登录标签位
        Event::trigger('frontendInit', $this->auth);
    }

    public function ipLimit()
    {
        $iplimit = 20;
        $ipinterval = 10; // 时间限制
        // 键名用于Redis中存储计数器的值
        $ip = get_client_ip();
        $ipkey = RedisKey::IP_REQUEST_LIMIT.$ip;
        // 检查计数器是否存在，如果不存在，则设置初始值为0，并设置过期时间
        if (!RedisUtil::exists($ipkey)) {
            RedisUtil::setEx($ipkey,$ipinterval, 0);
        }

        // 检查计数器的值是否超过限制
        $count = RedisUtil::getValue($ipkey);
        if ($iplimit >0 && $count > $iplimit) {
            RedisUtil::set(RedisKey::IP_REQUEST_LIMIT_OVER.$ip,$count);
            $this->error(__("ip发送请求次数超出限制。{$ipinterval}s内限制{$iplimit}次"));
        }

        if($iplimit >0){
            // 增加计数器的值
            RedisUtil::incr($ipkey);
        }
    }

    public function userLimit(){
        //判断频率
        $limit = get_sys_config('user_request_limit_num'); // 允许的发送次数
        $interval = 60; // 时间限制
        // 键名用于Redis中存储计数器的值
        $key = RedisKey::USER_REQUEST_LIMIT.$this->auth->getUser()->id;
        // 检查计数器是否存在，如果不存在，则设置初始值为0，并设置过期时间
        if (!RedisUtil::exists($key)) {
            RedisUtil::setEx($key,$interval, 0);
        }

        // 检查计数器的值是否超过限制
        $count = RedisUtil::getValue($key);
        if ($limit >0 && $count > $limit) {
            RedisUtil::set(RedisKey::USER_REQUEST_LIMIT_OVER.$this->auth->getUser()->id,$count);
            $this->error(__("用户发送请求次数超出限制。{$interval}s内限制{$limit}次"));
        }

        if($limit >0){
            // 增加计数器的值
            RedisUtil::incr($key);
        }
    }

}