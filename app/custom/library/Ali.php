<?php

namespace app\custom\library;

use AlibabaCloud\SDK\Cloudauth\V20190307\Cloudauth;
use AlibabaCloud\SDK\Cloudauth\V20190307\Models\DescribeFaceVerifyRequest;
use AlibabaCloud\SDK\Cloudauth\V20190307\Models\InitFaceVerifyRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use app\common\model\BussinessLog;
use Darabonba\OpenApi\Models\Config;
use think\facade\Config as Con;

class Ali
{
    /**
     * @return Cloudauth
     */
    public static function createClient()
    {
        // 工程代码泄露可能会导致 AccessKey 泄露，并威胁账号下所有资源的安全性。以下代码示例仅供参考。
        // 建议使用更安全的 STS 方式，更多鉴权访问方式请参见：https://help.aliyun.com/document_detail/311677.html。
        $config = new Config([
            // 必填，请确保代码运行环境设置了环境变量 ALIBABA_CLOUD_ACCESS_KEY_ID。
            "accessKeyId" => Con::get('ali.access_key_id'),
            // 必填，请确保代码运行环境设置了环境变量 ALIBABA_CLOUD_ACCESS_KEY_SECRET。
            "accessKeySecret" => Con::get("ali.access_key_secret")
        ]);
        // Endpoint 请参考 https://api.aliyun.com/product/Cloudauth
        $config->endpoint = "cloudauth.aliyuncs.com";
        return new Cloudauth($config);
    }

    /**
     * 初始化人脸验证请求
     * @param $params
     * @return \AlibabaCloud\SDK\Cloudauth\V20190307\Models\InitFaceVerifyResponse
     */
    public static function initFaceVerify($params, $callbackUr = ''):array
    {
        $initFaceVerifyRequest = new InitFaceVerifyRequest([
            "sceneId" => Con::get("ali.scene_id"), //认证场景ID
            "outerOrderNo" => Con::get("ali.outer_order_no"), //业务唯一标识
            "productCode" => "ID_PRO", // 固定值，要接入的认证方案
            "certType" => "IDENTITY_CARD", // 固定值，证件类型
            "certName" => $params['certName'],
            "certNo" => $params['certNo'],
            "returnUrl" => $params['returnUrl'] ?? '', // 业务页面回调地址
            "metaInfo" => $params['metaInfo'], // 环境参数
            "model" => "MULTI_ACTION", //活体检测类型
            "callbackUrl" => $callbackUr, // 认证结果的回调通知地址
        ]);
        BussinessLog::record('initFaceVerify认证数据：' . json_encode($initFaceVerifyRequest, JSON_UNESCAPED_UNICODE));

        $runtime = new RuntimeOptions([]);
        $client = self::createClient();
        // 复制代码运行请自行打印 API 的返回值
        $result = $client->initFaceVerifyWithOptions($initFaceVerifyRequest, $runtime);

        $result = json_decode(json_encode($result->body), true);
        BussinessLog::record('initFaceVerify返回数据：' . json_encode($result, JSON_UNESCAPED_UNICODE));

        if ($result['code'] == 200 && !empty($params['returnUrl'])) {
            // 解决在某些情况短链无法访问的问题
            $html_content = file_get_contents($result['resultObject']['certifyUrl']);
            // 使用正则表达式匹配目标地址
            preg_match('/http:\/\/m\.asmlink\.cn\/alicom\.do\?xpsu=([^\'"]+)/', $html_content, $matches);

            if (isset($matches[1])) {
                $result['resultObject']['certifyUrl'] = urldecode($matches[1]);
            }

        } elseif ($result['code'] == 401) {
            $result['message'] = '身份证信息不合法';
        }
        return $result;

    }

    /**
     * 获取认证结果
     * @param $certifyId
     * @return array
     */
    public static function certificationResults($certifyId = ''):array
    {

        $describeFaceVerifyRequest = new DescribeFaceVerifyRequest([
            "sceneId" => Con::get("ali.scene_id"),
            "certifyId" => $certifyId
        ]);
        $runtime = new RuntimeOptions([]);
        $client = self::createClient();
        // 复制代码运行请自行打印 API 的返回值
        $result = $client->describeFaceVerifyWithOptions($describeFaceVerifyRequest, $runtime);

        return json_decode(json_encode($result->body), true);
    }
}
