<?php

namespace MiniProgram\Api;

use MiniProgram\ApiBase;
use MiniProgram\ApiException;
use MiniProgram\Model\Token;

class AccessToken extends ApiBase
{

    /**
     * 获取小程序全局唯一后台接口调用凭据
     * @return Token
     * @throws ApiException
     */
    public function getAccessToken()
    {
        $appid = $this->miniProgram->getAppid();
        $secret = $this->miniProgram->getSecret();

        if (empty($appid) || empty($secret)) {
            $msg = 'params error';
            $this->addError($msg, [
                'appid' => $appid,
                'secret' => $secret,
            ]);
            throw new ApiException($msg);
        }

        $url = "https://api.weixin.qq.com/cgi-bin/token";
        $data = [
            'grant_type'  => 'client_credential',
            'appid'  => $appid,
            'secret'  => $secret,
        ];
        $res = $this->httpRequest('GET', $url, $data);

        $code = isset($res['errcode']) ? $res['errcode'] : 0;
        $msg = isset($res['errmsg']) ? $res['errmsg'] : 'ok';
        $access_token = isset($res['access_token']) ? $res['access_token'] : '';
        $expires_in = isset($res['expires_in']) ? $res['expires_in'] : 0;

        if ($code != 0 || empty($access_token)) {
            $this->addError($msg, $res);
            throw new ApiException($msg, $code);
        }

        $Token = new Token();
        $Token->access_token = $access_token;
        $Token->expires_in = $expires_in;

        return $Token;
    }

}
