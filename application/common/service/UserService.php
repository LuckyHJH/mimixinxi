<?php
namespace app\common\service;

use app\common\model\UserModel;
use think\Model;

class UserService extends Model
{
    /**
     * 获取某用户的信息
     * @param $id
     * @return array
     * @throws \Exception
     */
    public function getCacheById($id)
    {
        $cache_key = 'user_' . $id;
        $data = cache($cache_key);
        if (empty($data)) {

            $data = UserModel::get($id);
            if (empty($data)) {
                exception('该记录不存在', 500);
            }

            $data and cache($cache_key, $data);
        }
        return $data;
    }

    /**
     * 更新微信用户信息
     * @param $openid
     * @param $userInfo
     * @param string $session_key
     * @return array|bool
     * @throws \Exception
     */
    public function updateUserInfo($openid, $userInfo, $session_key = '')
    {
        if (empty($openid) || !isset($userInfo['nickName']) || !isset($userInfo['avatarUrl'])) {
            return false;
        }
        $avatar = $this->downloadAvatar($userInfo['avatarUrl'], $openid);

        $row = UserModel::get(['openid'=>$openid]);
        if ($row) {
            $row->nickname = $userInfo['nickName'];
            $row->avatar = $avatar;
            $row->weixin_info = json_encode($userInfo);
            $row->update_time = time();
            $session_key and $row->session_key = $session_key;
            $row->save();
            $result = $row->toArray();
        } else {
            $row = [
                'openid' => $openid,
                'nickname' => $userInfo['nickName'],
                'avatar' => $avatar,
                'weixin_info' => json_encode($userInfo),
                'create_time' => time(),
            ];
            $session_key and $row['session_key'] = $session_key;
            $result = UserModel::create($row)->toArray();
        }
        return $result;
    }

    /**
     * 下载头像到本地
     * @param $url
     * @param $openid
     * @return bool|string 成功时返回头像路径
     */
    public function downloadAvatar($url, $openid)
    {
        $data = file_get_contents($url);
        if (empty($data) || empty($openid)) {
            return false;
        }

        $fileName = md5($openid);
        $path = substr($fileName, 0, 2);
        $uploadDir = "/uploads/avatar/{$path}";
        $path = ROOT_PATH . '/public' . $uploadDir;
        $avatar = "{$uploadDir}/{$fileName}";
        if (!(is_dir($path) || mkdir($path, 0755, true))) {
            return false;
        }

        $res = file_put_contents("{$path}/{$fileName}", $data);
        return $res ? $avatar : $url;
    }
}