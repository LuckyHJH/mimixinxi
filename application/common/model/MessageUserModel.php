<?php
namespace app\common\model;

use think\Model;

class MessageUserModel extends Model
{
    protected $name = 'message_user';

    /**
     * 用户看消息的次数
     * @param $user_id
     * @param $message_id
     * @return int
     * @throws \think\Exception
     */
    public function readCount($user_id, $message_id)
    {
        $count = $this->where([
            'user_id' => $user_id,
            'message_id' => $message_id,
        ])->count('id');
        return intval($count);
    }

    /**
     * 消息被查看的人数
     * @param $message_id
     * @return int
     * @throws \think\Exception
     */
    public function userCount($message_id)
    {
        $count = $this->where([
            'message_id' => $message_id,
        ])->group('user_id')->count('id');
        return intval($count);
    }
}