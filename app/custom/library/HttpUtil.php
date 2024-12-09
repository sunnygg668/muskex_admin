<?php

namespace app\custom\library;

use GuzzleHttp\Client;

class HttpUtil
{
    public static function getClientProxyOptions($apiKey = null, $body = null): array
    {
        $headers = [];
        if ($apiKey) {
            $headers['TRON-PRO-API-KEY'] = $apiKey;
        }
        return [
            "headers" => $headers,
            "body" => $body
        ];
    }

    public static function getUrl($url, $apiKey = null, $body = null): array
    {
        try {
            $options = self::getClientProxyOptions($apiKey, $body);
            $client = new Client();
            $response = $client->get($url, $options);
            $resData = $response->getBody()->getContents();
            return json_decode($resData, true) ?? [];
        } catch (\Exception $e) {
            return array_merge(['msg' => $e->getMessage()], $e->getTrace());
        }
    }

    public static function postUrl($url, $apiKey = null, $body = null): array
    {
        try {
            $options = self::getClientProxyOptions($apiKey, $body);
            $client = new Client();
            $response = $client->post($url, $options);
            $resData = $response->getBody()->getContents();
            return json_decode($resData, true) ?? [];
        } catch (\Exception $e) {
            return array_merge(['msg' => $e->getMessage()], $e->getTrace());
        }

    }

}