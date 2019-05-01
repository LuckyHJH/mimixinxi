<?php
namespace app\common\service;

use app\common\model\MessageContentModel;
use think\Model;

class MessageContentService extends Model
{
    /**
     * 获取内容列表
     * @param $message_id
     * @return array
     * @throws \Exception
     */
    public function getContents($message_id)
    {
        $contents = [];
        $list = (new MessageContentModel())->getByMessageId($message_id);
        foreach ($list as $item) {
            if ($item['type'] == 'text') {
                $contents[] = $item;
            } elseif ($item['type'] == 'image') {
                $item['data'] = url('api/message/image', ['message_id' => $message_id, 'url' => $item['data']], false, true);
                $contents[] = $item;
            } else {
                continue;
            }
        }
        return $list;
    }

    /**
     * 获取图片二进制内容
     * @param $user_id
     * @param $message_id
     * @param $fileName
     * @return false|string
     * @throws \Exception
     */
    public function getImageContents($user_id, $message_id, $fileName)
    {
        //判断权限
        $canUserRead = (new MessageService())->canUserRead($message_id, $user_id);
        if (!$canUserRead['can_read']) {
            exception($canUserRead['reason'], 403);
        }

        $image_contents = file_get_contents($this->getUrlByFileName($fileName, '.'));
        if (empty($image_contents)) {
            exception('图片不存在', 404);
        }

        return $image_contents;
    }

    /**
     * 通过图片名称获取相对路径
     * @param $fileName
     * @param string $prefix
     * @return string
     */
    public function getUrlByFileName($fileName, $prefix = '')
    {
        $path = substr($fileName, 0, 2);
        return $prefix . "/uploads/message/{$path}/{$fileName}";
    }

    /**
     * 获取信息的标题
     * @param $message_id
     * @return string
     * @throws \Exception
     */
    public function getTitle($message_id)
    {
        $MessageContentModel = new MessageContentModel();
        //先获取第一条文本内容
        $row = $MessageContentModel->field('data')->where([
            'message_id' => $message_id,
            'type' => 1,
            'is_del' => 0,
        ])->order('id asc')->find();

        if (empty($row)) {
            //没有的话就算图片数量
            $count = $MessageContentModel->field('data')->where([
                'message_id' => $message_id,
                'type' => 2,
                'is_del' => 0,
            ])->order('id asc')->count();
            if (empty($count)) {
                $text = '';
            } else {
                $text = "[{$count}张图片]";
            }
        } else {
            $text = strval($row['data']);
        }

        return $text;
    }
}