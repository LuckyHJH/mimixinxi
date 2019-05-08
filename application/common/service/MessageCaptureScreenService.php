<?php
namespace app\common\service;

use app\common\model\MessageCaptureScreenModel;
use app\common\model\MessageModel;
use think\Model;

class MessageCaptureScreenService extends Model
{
    /**
     * 用户截屏事件
     * @param $user_id
     * @param $message_id
     * @return bool
     * @throws \Exception
     */
    public function add($user_id, $message_id)
    {
        $messageRow = (new MessageModel())->getMessage($message_id);
        if (empty($messageRow)) {
            return false;
        }

        if ($messageRow['user_id'] == $user_id) {
            return true;
        }

        $result = MessageCaptureScreenModel::create([
            'message_id' => $message_id,
            'user_id' => $user_id,
            'create_time' => time(),
        ]);
        return $result ? true : false;
    }

    /**
     * 用户总截屏次数
     * @param $user_id
     * @return int
     * @throws \Exception
     */
    public function totalCount($user_id)
    {
        return (int)(new MessageCaptureScreenModel())->where(['user_id' => $user_id])->count('id');
    }
}