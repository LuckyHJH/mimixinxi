<?php

namespace app\common\model;

use think\Model;

class MessageModel extends Model
{
    // 表名
    protected $name = 'message';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    
    // 追加属性
    protected $append = [
        'create_time_text',
        'delete_time_text'
    ];

    static $rules = [
        'reading_amount' => 0,//阅读次数
        'second_limit' => 0,
        'user_limit' => 0,
    ];

    static $properties = [
        'anonymous' => 0,
    ];


    /**
     * 通过message_id获取消息
     * @param $message_id
     * @return MessageModel|null
     * @throws \Exception
     */
    public function getMessage($message_id)
    {
        $messageRow = MessageModel::get($message_id);
        if (empty($messageRow) || $messageRow['is_del']) {
            exception('该消息不存在或已被删除', 404);
        }
        return $messageRow;
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getDeleteTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['delete_time']) ? $data['delete_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setDeleteTimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }


    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
