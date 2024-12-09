<?php

namespace app\api\controller;

use app\custom\library\OssUtil;
use ba\Exception;
use OSS\OssClient;
use Throwable;
use think\Response;
use app\common\library\Upload;
use app\common\controller\Frontend;

class Ajax extends Frontend
{
//    protected array $noNeedLogin = ['area', 'buildSuffixSvg'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function upload(): void
    {
        $file = $this->request->file('file');
        try {
            $upload     = new Upload($file);
            $attachment = $upload->upload(null, 0, $this->auth->id);
            unset($attachment['create_time'], $attachment['quote']);

            // 上传文件到OSS
            $fileUrl = ltrim($attachment['url'], '/');
            OssUtil::upload($fileUrl);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('File uploaded successfully'), [
            'file' => $attachment ?? []
        ]);
    }
    public function area(): void
    {
        $this->success('', get_area());
    }

    public function buildSuffixSvg(): Response
    {
        $suffix     = $this->request->param('suffix', 'file');
        $background = $this->request->param('background');
        $content    = build_suffix_svg((string)$suffix, (string)$background);
        return response($content, 200, ['Content-Length' => strlen($content)])->contentType('image/svg+xml');
    }
}
