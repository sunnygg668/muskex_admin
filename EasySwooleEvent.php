<?php


namespace EasySwoole\EasySwoole;


use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\FastDb\FastDb;

class EasySwooleEvent implements Event
{
    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');

        // 注册方式1：在 initialize 方法中注册连接
        $config = new \EasySwoole\FastDb\Config([
            'name'              => 'default',    // 设置 连接池名称，默认为 default
            'host'              => '127.0.0.1',  // 设置 数据库 host
            'user'              => 'root', // 设置 数据库 用户名
            'password'          => '123456', // 设置 数据库 用户密码
            'database'          => 'p_project_exchange', // 设置 数据库库名
            'port'              => 8889,         // 设置 数据库 端口
            'timeout'           => 5,            // 设置 数据库连接超时时间
            'charset'           => 'utf8',       // 设置 数据库字符编码，默认为 utf8
            'autoPing'          => 5,            // 设置 自动 ping 客户端链接的间隔
            'useMysqli'         => false,        // 设置 不使用 php mysqli 扩展连接数据库
            // 配置 数据库 连接池配置，配置详细说明请看连接池组件 https://www.easyswoole.com/Components/Pool/introduction.html
            // 下面的参数可使用组件提供的默认值
            'intervalCheckTime' => 15 * 1000,    // 设置 连接池定时器执行频率
            'maxIdleTime'       => 10,           // 设置 连接池对象最大闲置时间 (秒)
            'maxObjectNum'      => 20,           // 设置 连接池最大数量
            'minObjectNum'      => 5,            // 设置 连接池最小数量
            'getObjectTimeout'  => 3.0,          // 设置 获取连接池的超时时间
            'loadAverageTime'   => 0.001,        // 设置 负载阈值
        ]);
        // 或使用对象设置属性方式进行配置
        // $config->setName('default');
        // $config->setHost('127.0.0.1');
        FastDb::getInstance()->addDb($config);
        // 或在注册时指定连接池的名称
        // FastDb::getInstance()->addDb($config, $config['name']);
    }

    public static function mainServerCreate(EventRegister $register)
    {

    }
}