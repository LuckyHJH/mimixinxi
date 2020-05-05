<?php
namespace app\common\service;

use app\common\model\MessageReportModel;
use think\Model;

class MessageReportService extends Model
{
    /**
     * 用户投诉
     * @param $user_id
     * @param $message_id
     * @param int $type
     * @return bool
     * @throws \Exception
     */
    public function add($user_id, $message_id, $type = 9)
    {
        $result = MessageReportModel::create([
            'message_id' => $message_id,
            'user_id' => $user_id,
            'type' => $type,
            'create_time' => time(),
        ]);
        notice('投诉', "信息ID：$message_id", "$user_id");
        return $result ? true : false;
    }

}