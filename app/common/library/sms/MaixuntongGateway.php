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
class MaixuntongGateway extends Gateway
{
    use HasHttpRequest;
    const ENDPOINT_URL = 'http://mxthk.weiwebs.cn/msg/HttpBatchSendSM';//海外      https://www.weiwebs.cn/msg/HttpBatchSendSM国内


    private $codeMsgArr = [
        "0" =>"提交成功",
        "101" =>"无此用户",
        "102" =>"密码错",
        "103" =>"提交过快（提交速度超过流速限制）",
        "104" =>"系统忙（因平台侧原因，暂时无法处理提交的短信）",
        "105" =>"敏感短信（短信内容包含敏感词）",
        "106" =>"消息长度错（>700或<=0）",
        "107" =>"包含错误的手机号码",
        "108" =>"手机号码个数错（群发>50000或<=0;单发>200或<=0）",
        "109" =>"无发送额度（该用户可用短信数已使用完）",
        "110" =>"不在发送时间内",
        "111" =>"超出该账户当月发送额度限制",
        "112" =>"无此产品，用户没有订购该产品",
        "113" =>"extno格式错（非数字或者长度不对）",
        "115" =>"自动审核驳回",
        "116" =>"签名不合法，未带签名（用户必须带签名的前提下）",
        "117" =>"IP地址认证错,请求调用的IP地址不是系统登记的IP地址",
        "118" =>"用户没有相应的发送权限",
        "119" =>"用户已过期",
        "120" =>"内容不在白名单模板中",
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
        $time = date('YmdHis');
        $params = [
            'account' => $gateway['account'],
            'ts' => $time,
            'pswd' => md5($gateway['account'].$gateway['pswd'].$time),
            'mobile' => $to,
            'msg' => $message['content'],
            'needstatus'=> true,
        ];
        //echo "<pre>";print_r($params);
        try {
            $result = $this->post(self::ENDPOINT_URL, $params);
        }catch (\Exception $e){
            throw new GatewayErrorException("网络异常，请稍后重试", "502");
        }

        $result = explode(",",$result);
        if (count($result) != 2) {
            throw new GatewayErrorException("请求异常，请重试", "500", $result);
        }

        $code = $result[1];
        if(!array_key_exists($code,$this->codeMsgArr)) {
            throw new GatewayErrorException("code码错误：".$code, "501", $result);
        }else{
            if(is_numeric($code) && $code == 0){
                $result = ["code"=>$code,"massage"=>$this->codeMsgArr[$code]];
            }else{
                throw new GatewayErrorException("发送失败：".$this->codeMsgArr[$code], $code, $result);
            }
        }

        return $result;
    }
}
