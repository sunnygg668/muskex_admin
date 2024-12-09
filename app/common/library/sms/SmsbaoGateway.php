<?php

/*
 * This file is part of the overtrue/easy-sms.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\library\sms;

use Overtrue\EasySms\Exceptions\GatewayErrorException;
use Overtrue\EasySms\Support\Config;
use Overtrue\EasySms\Traits\HasHttpRequest;

/**
 * Class AliyunGateway.
 *
 * @author carson <docxcn@gmail.com>
 *
 * @see https://help.aliyun.com/document_detail/55451.html
 */
class SmsbaoGateway extends Gateway
{
    use HasHttpRequest;
    const ENDPOINT_URL = 'http://api.smsbao.com/sms';


    private $codeMsgArr = [
        "0" => "短信发送成功",
        "-1" => "参数不全",
        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
        "30" => "密码错误",
        "40" => "账号不存在",
        "41" => "余额不足",
        "42" => "帐户已过期",
        "43" => "IP地址限制",
        "50" => "内容含有敏感词"
    ];


    /**
     * @return array
     *
     * @throws \Overtrue\EasySms\Exceptions\GatewayErrorException ;
     */
    public function send( $to,  $message,  $config)
    {
        $gatewayName = $config['default']['gateways'][0];
        $gateway = $config['gateways'][$gatewayName];
        $params = [
            'u' => $gateway['account'],
            'p' => md5($gateway['pswd']),
            'm' => $to,
            'c' => $message['content']
        ];
        //echo "<pre>";print_r($params);
        try {
            $result = file_get_contents(self::ENDPOINT_URL."?".http_build_query($params));
        }catch (\Exception $e){
            throw new GatewayErrorException("网络异常，请稍后重试", "502");
        }

        if (!is_numeric($result)) {
            throw new GatewayErrorException("请求异常，请重试", "500", [$result]);
        }

        $code = $result;
        if(!array_key_exists($code,$this->codeMsgArr)) {
            throw new GatewayErrorException("code码错误：".$code, "501", [$result]);
        }else{
            if(is_numeric($code) && $code == 0){
                $result = ["code"=>$code,"massage"=>$this->codeMsgArr[$code]];
            }else{
                throw new GatewayErrorException("发送失败：".$this->codeMsgArr[$code], $code, [$result]);
            }
        }

        return $result;
    }
}
