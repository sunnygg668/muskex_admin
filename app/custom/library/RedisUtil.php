<?php

namespace app\custom\library;

use Closure;
use Predis\Client;
use think\Container;
use think\facade\Config;

class RedisUtil
{

    private static $_instance = null; //静态实例

    //获取静态实例
    public static  function getRedis(){
        if(!self::$_instance){
            $config = Config::get('redis');
            self::$_instance = new Client($config);
        }
        return self::$_instance;
    }
    
    public static function exists($key)
    {
        $client = self::getRedis();
        return $client->exists($key);
    }
    public static function set($key, $value,$expireResolution = null, $expireTTL = null,$flag = null)
    {
        $value = json_encode($value, true);
        $client = self::getRedis();
        if($flag){
            return $client->set($key, $value,$expireResolution,$expireTTL,$flag);
        }else{
            $client->set($key, $value);
        }
    }

    public static function setEx($key, $seconds, $value)
    {
        $value = json_encode($value, true);
        $client = self::getRedis();
        $client->setex($key, $seconds, $value);
    }

    public static function setNx($key, $value)
    {
        $value = json_encode($value, true);
        $client = self::getRedis();
        return $client->setnx($key,$value);
    }

    public static function ttl($key)
    {
        $client = self::getRedis();
        return $client->ttl($key);
    }

    public static function expire($key,$seconds)
    {
        $client = self::getRedis();
        $client->expire($key, $seconds);
    }

    public static function incr($key, $seconds=0) {
        $client = self::getRedis();
        $client->incr($key);
        if ($seconds > 0) {
            $client->expire($key, $seconds);
        }
    }

    public static function get($key): array
    {
        $client = self::getRedis();
        $value = $client->get($key) ?? '[]';
        return json_decode($value, true) ?? [];
    }

    public static function getValue($key): string
    {
        $client = self::getRedis();
        $value = $client->get($key) ?? '';
        return $value;
    }

    public static function del($key): void
    {
        $client = self::getRedis();
        $client->del($key);
    }

    /**
     * 如果不存在则写入缓存
     * @access public
     * @param string                             $name   缓存变量名
     * @param mixed                              $value  存储数据
     * @param int|DateInterval|DateTimeInterface $expire 有效时间 0为永久
     * @return mixed
     */
    public static function remember($name, $value, $expire = null)
    {
        if (self::exists($name)) {
            if (($hit = self::get($name)) !== null) {
                return $hit;
            }
        }

        $time = time();

        while ($time + 5 > time() && self::exists($name . '_lock')) {
            // 存在锁定则等待
            usleep(200000);
        }

        try {
            // 锁定
            self::set($name . '_lock', true);

            if ($value instanceof Closure) {
                // 获取缓存数据
                $value = Container::getInstance()->invokeFunction($value);

                $value = $value?$value->toArray():[];
            }

            if($expire){
                // 缓存数据
                self::setEx($name,$expire,$value);
            }else{
                // 缓存数据
                self::set($name,$value);
            }

            // 解锁
            self::del($name . '_lock');
        } catch (\Exception $e) {
            self::del($name . '_lock');
            throw $e;
        }

        return $value;
    }
}
