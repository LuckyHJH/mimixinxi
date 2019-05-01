<?php
namespace app\common\model;

use think\Model;

class MessageContentModel extends Model
{
    protected $name = 'message_content';

    static $typeId = [
        'text' => 1,
        'image' => 2,
    ];

    public function getTypeAttr($value)
    {
        $type = array_flip(self::$typeId);
        return $type[$value];
    }


    /**
     * 通过message_id获取信息内容
     * @param $message_id
     * @return array
     * @throws \Exception
     */
    public function getByMessageId($message_id)
    {
        $data = $this->field('type,data')->where([
            'message_id' => $message_id,
            'is_del' => 0,
        ])->order('id asc')->select();
        return (array)$data;
    }
}