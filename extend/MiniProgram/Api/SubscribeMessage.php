<?php

namespace MiniProgram\Api;

use MiniProgram\ApiBase;
use MiniProgram\ApiException;

/**
 * 订阅消息
 * Class SubscribeMessage
 * @package MiniProgram\Api
 */
class SubscribeMessage extends ApiBase
{
    /**
     * 发送订阅消息
     * @param $openid
     * @param $template_id
     * @param $data
     * @param string $page
     * @return bool 发送成功返回true，没有剩余推送次数或被拒绝就返回false
     * @throws ApiException
     */
    public function send($openid, $template_id, $data, $page = '')
    {
        if (empty($openid) || empty($template_id)) {
            $msg = 'params error';
            $this->addError($msg, [
                'openid' => $openid,
                'template_id' => $template_id,
            ]);
            throw new ApiException($msg);
        }

        $accessToken = $this->miniProgram->getApiToken();
        $url = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token={$accessToken}";
        $data = [
            'touser'  => $openid,
            'template_id'  => $template_id,
            'data'  => $data,
        ];
        $page and $data['page'] = $page;
        $res = $this->httpRequest('JSON', $url, $data);

        $code = isset($res['errcode']) ? $res['errcode'] : 9999;
        $msg = isset($res['errmsg']) ? $res['errmsg'] : 'httpRequest failed';

        if ($code == 0) {
            return true;

        } elseif ($code == 43101) {
            //用户拒绝接受消息，如果用户之前曾经订阅过，则表示用户取消了订阅关系
            //或没有剩余推送次数
            return false;

        } else {
            $this->addError($msg, $res);
            throw new ApiException($msg, $code);
        }
    }

}
