<?php

namespace app\custom\library;

class KfpaySign
{
    public static function getSignContent($params) {
        unset($params['sign']);
        unset($params['sign_type']);
        unset($params['attach']);
        unset($params['version']);
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (isset($v) && !empty($v) && $v != ""  && "@" != substr($v, 0, 1)) {
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }

    /** RSA2算法签名 */
    public static function RSA2sign($str, $rsaPrivateKey) {
        //格式化密钥，添加头尾
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($rsaPrivateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        openssl_sign($str, $sign, $res, OPENSSL_ALGO_SHA256);

        $sign = base64_encode($sign);
        return $sign;
    }

    /** 验签 */
    public static function RSA2verify($str, $sign, $rsaPublicKey) {
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($rsaPublicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        ($res) or die('RSA公钥错误。请检查公钥文件格式是否正确');
        //调用openssl内置方法验签，返回bool值
        $result = FALSE;
        $result = (openssl_verify($str,base64_decode($sign), $res, OPENSSL_ALGO_SHA256)===1);
        return $result;
    }

    public static function post($url, $post_data = '', $header = '', $timeout = 11) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        if (preg_match("/https/i", $url)) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 信任任何证书
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名
        }
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0(for adpay)");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER,1);
        if ($header) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        if ($post_data) {
            curl_setopt($curl, CURLOPT_POST, 1);
            if (is_array($post_data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
            }
        }
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        return $data;
    }
}
