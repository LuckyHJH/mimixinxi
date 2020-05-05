<?php
namespace app\api\controller;

use app\common\controller\Api;
use app\common\service\UserService;

class User extends Api
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 登录
     * @throws \Exception
     */
    public function login()
    {
        $code = $this->input('code');
        $userInfo = $this->input('userInfo');

        $this->checkParams($code);

        $aResult = $this->mpLogin($code, $userInfo);
        if (!isset($aResult['id'])) {
            $this->error('登录失败请稍后再试');
        }

        $this->output([
            'token' => $this->auth->encryptToken($aResult['id']),
        ]);
    }


    /**
     * 小程序登录
     * @param $code
     * @param $userInfo
     * @return array|bool
     * @throws \Exception
     */
    private function mpLogin($code, $userInfo)
    {
        $mpInfo = $this->codeToOpenid($code);
        return (new UserService())->updateUserInfo($mpInfo['openid'], $userInfo, $mpInfo['session_key']);
    }

    /**
     * 小程序登录（用code获取openid）
     * @param $code
     * @return array 微信接口返回的，有openid和session_key
     * @throws \Exception
     */
    private function codeToOpenid($code)
    {
        $sAppId = \think\Env::get('wx_appid');
        $sAppSecret = \think\Env::get('wx_secret');
        $sUrl = "https://api.weixin.qq.com/sns/jscode2session?appid={$sAppId}&secret={$sAppSecret}&js_code={$code}&grant_type=authorization_code";

        $sJsonResult = file_get_contents($sUrl);
        $aResult = json_decode($sJsonResult, true);
        //$aResult = ['session_key' => 'f9umDYdqoz0wgacCrLDYFA==', 'openid' => 'o4W6G5Fz0M5imbBAIFxSfmPMmKe8'];

        if (empty($aResult)) {
            exception('微信服务器错误，请稍后重试', 600);
        }
        if (isset($aResult['errcode']) && $aResult['errcode'] != 0) {
            exception('登陆失败：' . $aResult['errmsg'], $aResult['errcode']);
        }

        return $aResult;
    }

}
