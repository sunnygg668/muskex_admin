<?php

namespace app\custom\library;

use ba\Exception;
use OSS\OssClient;

class OssUtil
{
    public static function upload($fileUrl)
    {
        $uploadConfig = get_sys_config('', 'upload');
        if (!$uploadConfig['upload_access_id'] || !$uploadConfig['upload_secret_key'] || !$uploadConfig['upload_bucket']) {
            throw new Exception('Alioss 参数缺失');
        }
        $OssClient = new OssClient($uploadConfig['upload_access_id'], $uploadConfig['upload_secret_key'], 'http://' . $uploadConfig['upload_url'] . '.aliyuncs.com');
        $OssClient->uploadFile($uploadConfig['upload_bucket'], $fileUrl, public_path() . $fileUrl);
    }

    public static function doesObjectExist($fileUrl): bool
    {
        $uploadConfig = get_sys_config('', 'upload');
        if (!$uploadConfig['upload_access_id'] || !$uploadConfig['upload_secret_key'] || !$uploadConfig['upload_bucket']) {
            throw new Exception('Alioss 参数缺失');
        }
        $OssClient = new OssClient($uploadConfig['upload_access_id'], $uploadConfig['upload_secret_key'], 'http://' . $uploadConfig['upload_url'] . '.aliyuncs.com');
        return $OssClient->doesObjectExist($uploadConfig['upload_bucket'], $fileUrl);
    }
}