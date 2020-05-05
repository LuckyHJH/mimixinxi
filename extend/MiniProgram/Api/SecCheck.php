<?php

namespace MiniProgram\Api;

use MiniProgram\ApiBase;
use MiniProgram\ApiException;

/**
 * 安全内容检测接口
 * Class SecCheck
 * @package MiniProgram\Api
 */
class SecCheck extends ApiBase
{
    /**
     * 文本是否为风险内容
     * @param string $content
     * @return bool
     * @throws ApiException
     */
    public function messageIsRisky($content)
    {
        if (empty($content)) {
            $msg = 'params error';
            $this->addError($msg, [
                'content' => $content,
            ]);
            throw new ApiException($msg);
        }

        $max_length = 500 * 1024;//不超过500KB
        if (strlen($content) > $max_length) {
            throw new ApiException('message is too long');
        }

        $accessToken = $this->miniProgram->getApiToken();
        $url = "https://api.weixin.qq.com/wxa/msg_sec_check?access_token={$accessToken}";
        $data = [
            'content'  => $content,
        ];
        $res = $this->httpRequest('JSON', $url, $data);

        $code = isset($res['errcode']) ? $res['errcode'] : 9999;
        $msg = isset($res['errmsg']) ? $res['errmsg'] : 'httpRequest failed';

        if ($code == 0) {
            return false;

        } elseif ($code == 87014) {
            //当content内含有敏感信息，则返回87014
            return true;

        } else {
            $this->addError($msg, $res);
            throw new ApiException($msg, $code);
        }
    }

    /**
     * 图片是否为风险内容
     * @param string $file 文件
     * @return bool
     * @throws ApiException
     */
    public function imageIsRisky($file)
    {
        if (empty($file) || !is_file($file)) {
            $msg = 'params error';
            $this->addError($msg, [
                'file' => $file,
            ]);
            throw new ApiException($msg);
        }

        //像素不超过750x1334
        list($width, $height) = getimagesize($file);
        if ($width > 750 || $height > 1334) {
            throw new ApiException('image is too large');
        }

        $accessToken = $this->miniProgram->getApiToken();
        $url = "https://api.weixin.qq.com/wxa/img_sec_check?access_token={$accessToken}";
        $data = [
            'media'  => $file,
        ];
        $res = $this->httpRequest('FILE', $url, $data);

        $code = isset($res['errcode']) ? $res['errcode'] : 9999;
        $msg = isset($res['errmsg']) ? $res['errmsg'] : 'httpRequest failed';

        if ($code == 0) {
            return false;

        } elseif ($code == 87014) {
            //当content内含有敏感信息，则返回87014
            return true;

        } else {
            $this->addError($msg, $res);
            throw new ApiException($msg, $code);
        }
    }

}
