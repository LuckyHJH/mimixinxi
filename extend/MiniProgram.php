<?php

use MiniProgram\Api\AccessToken;
use MiniProgram\ApiException;
use think\Cache;

/**
 * 自定义实现小程序基类
 * Class MiniProgram
 */
class MiniProgram extends \MiniProgram\MiniProgram
{
    public function __construct($appid = '', $secret = '')
    {
        empty($appid) and $appid = \think\Env::get('wx_appid');
        empty($secret) and $secret = \think\Env::get('wx_secret');
        parent::__construct($appid, $secret);
    }

    /**
     * @return string
     */
    public function getApiToken()
    {
        $cache_name = 'wx_token_' . $this->getAppid();
        $access_token = Cache::get($cache_name);
        if ($access_token) {
            return $access_token;
        }

        $AccessToken = new AccessToken($this);
        try {
            $Token = $AccessToken->getAccessToken();
            $access_token = $Token->access_token;
            $this->setApiToken($access_token, $Token->expires_in);
            return $access_token;

        } catch (ApiException $apiException) {
            add_error_log($apiException->getMessage(), $AccessToken->getErrors());
            return '';
        }
    }

    /**
     * @param string $access_token
     * @param int $expire
     * @return bool
     */
    public function setApiToken($access_token, $expire = 7200)
    {
        $cache_name = 'wx_token_' . $this->getAppid();
        return Cache::set($cache_name, $access_token, $expire);
    }
}
