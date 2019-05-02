<?php
namespace app\common\service;

use app\common\model\MessageUserModel;
use think\Model;

class MessageUserService extends Model
{
    /**
     * 用户查看消息
     * @param $user_id
     * @param $message_id
     * @return MessageUserModel
     */
    public function read($user_id, $message_id)
    {
        return MessageUserModel::create([
            'message_id' => $message_id,
            'user_id' => $user_id,
            'create_time' => time(),
        ]);
    }
}