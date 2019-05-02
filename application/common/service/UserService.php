<?php
namespace app\common\service;

use app\common\model\UserModel;
use think\Model;

class UserService extends Model
{
    /**
     * 更新微信用户信息
     * @param $openid
     * @param $userInfo
     * @param string $session_key
     * @return array|bool
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function updateUserInfo($openid, $userInfo, $session_key = '')
    {
        if (empty($openid) || !isset($userInfo['nickName']) || !isset($userInfo['avatarUrl'])) {
            return false;
        }

        $row = UserModel::get(['openid'=>$openid]);
        if ($row) {
            $row->nickname = $userInfo['nickName'];
            $row->avatar = $userInfo['avatarUrl'];
            $row->weixin_info = json_encode($userInfo);
            $row->update_time = time();
            $session_key and $row->session_key = $session_key;
            $row->save();
            $result = $row->toArray();
        } else {
            $row = [
                'openid' => $openid,
                'nickname' => $userInfo['nickName'],
                'avatar' => $userInfo['avatarUrl'],
                'weixin_info' => json_encode($userInfo),
                'create_time' => time(),
            ];
            $session_key and $row['session_key'] = $session_key;
            $result = UserModel::create($row)->toArray();
        }
        return $result;
    }

}