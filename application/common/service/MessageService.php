<?php
namespace app\common\service;

use app\common\model\MessageContentModel;
use app\common\model\MessageModel;
use app\common\model\MessageUserModel;
use think\Config;
use think\Model;

class MessageService extends Model
{
    /**
     * 发布消息
     * @param $user_id
     * @param $contents
     * @param $rules
     * @param $properties
     * @return MessageModel
     * @throws \Exception
     */
    public function add($user_id, $contents, $rules, $properties = [])
    {
        if (empty($user_id) || empty($contents) || !is_array($contents) || empty($rules) || !is_array($rules)) {
            exception('param error', 400);
        }

        //阅读规则
        $_rules = MessageModel::$rules;
        foreach ($rules as $rule) {
            if (!isset($_rules[$rule['type']])) {
                exception('rule type error', 400);
            }

            $_rules[$rule['type']] = $rule['data'];
        }

        //其它属性
        $_properties = MessageModel::$properties;
        foreach ($properties as $property => $value) {
            if (!isset($_properties[$property])) {
                exception('property type error', 400);
            }

            $_properties[$property] = $value;
        }

        $time = time();
        $this->startTrans();

        $data = [
            'user_id' => $user_id,
            'create_time' => $time,
        ];
        $data = array_merge($data, $_rules);
        $data = array_merge($data, $_properties);

        //信息主表
        $row = MessageModel::create($data);
        $message_id = intval($row['id']);
        if (empty($message_id)) {
            exception('db error', 500);
        }

        //信息内容表
        foreach ($contents as $content) {
            if (!isset($content['type']) || !isset($content['data'])) {
                exception('content error', 400);
            }
            $type = MessageContentModel::$typeId[$content['type']];
            if (empty($type)) {
                exception('type error', 400);
            }

            $result = MessageContentModel::create([
                'user_id' => $user_id,
                'message_id' => $message_id,
                'type' => $type,
                'data' => $content['data'],
                'create_time' => $time,
            ]);
            if (empty($result)) {
                exception('db error', 500);
            }
        }

        $this->commit();
        return $row;
    }

    /**
     * 详情页面
     * @param $message_id
     * @param $user_id
     * @return array
     * @throws \Exception
     */
    public function detail($message_id, $user_id)
    {
        $canUserRead = $this->canUserRead($message_id, $user_id);
        $messageRow = $canUserRead['message'];
        $is_mine = $canUserRead['is_mine'];

        $data = [
            'id' => $messageRow['id'],
            'is_mine' => $is_mine,//是否我发布的
            'can_read' => $canUserRead['can_read'],//能否查看
            'reason' => $canUserRead['reason'],
            'time' => date('Y-m-d', $messageRow['create_time']),
        ];

        //规则
        $data['rules'] = [
            ['type' => 'reading_amount', 'data' => $messageRow['reading_amount']],
            ['type' => 'second_limit', 'data' => $messageRow['second_limit']],
            ['type' => 'user_limit', 'data' => $messageRow['user_limit']],
        ];

        //作者信息
        $data['user'] = $this->userInfo($messageRow['user_id'], $messageRow['anonymous']);

        return $data;
    }

    /**
     * 判断用户能否看消息
     * @param $message_id
     * @param $user_id
     * @return array
     * @throws \Exception
     */
    public function canUserRead($message_id, $user_id)
    {
        $messageRow = (new MessageModel())->getMessage($message_id);

        $is_mine = $messageRow['user_id'] == $user_id;

        $result = [
            'message' => $messageRow,
            'is_mine' => $is_mine,
            'can_read' => true,
            'reason' => '',
        ];

        //是否自己的
        if ($is_mine) {
            return $result;
        }

        $MessageUserModel = new MessageUserModel();
        $no_rules = true;//无规则限制

        if ($result['can_read'] && $messageRow['reading_amount']) {
            //判断次数
            $no_rules = false;
            $readCount = $MessageUserModel->readCount($user_id, $message_id);
            if ($readCount >= $messageRow['reading_amount']) {
                $result['can_read'] = false;
                $result['reason'] = '此消息已销毁';
            }
        }

        if ($result['can_read'] && $messageRow['user_limit']) {
            //判断人数
            $no_rules = false;
            $userCount = $MessageUserModel->userCount($message_id);
            if ($userCount >= $messageRow['user_limit']) {
                $result['can_read'] = false;
                $result['reason'] = '已超出人数限制，不能查看';
            }
        }

        if ($result['can_read'] && !$no_rules) {
            //判断是否截屏过，没设置规则的消息就不用判断
            $captureScreenTotalCount = (new MessageCaptureScreenService())->totalCount($user_id);
            if ($captureScreenTotalCount >= 3) {
                $result['can_read'] = false;
                $result['reason'] = '违规截屏次数超过要求，禁止查看消息';
            }
        }

        return $result;
    }

    /**
     * 查看信息内容
     * @param $message_id
     * @param $user_id
     * @return array
     * @throws \Exception
     */
    public function content($message_id, $user_id)
    {
        //判断权限
        $canUserRead = $this->canUserRead($message_id, $user_id);
        if (!$canUserRead['can_read']) {
            exception($canUserRead['reason'], 403);
        }

        //设置已阅
        if (!$canUserRead['is_mine']) {
            (new MessageUserService())->read($user_id, $message_id);
        }

        $content = (new MessageContentService())->getContents($message_id, $user_id);
        $data = [
            'content' => $content,
        ];
        return $data;
    }


    /**
     * 发布列表
     * @param $user_id
     * @param int $page
     * @return array
     * @throws \Exception
     */
    public function sendList($user_id, $page = 1)
    {
        $data = (new MessageModel())->field('id,create_time')->where([
            'user_id' => $user_id,
            'is_del' => 0,
        ])->order('id desc')->page($page, Config::get('page_size') ?: 20)->select();

        $list = [];
        $MessageContentService = new MessageContentService();

        foreach ($data as $item) {
            $title = $MessageContentService->getTitleCache($item['id']);
            $list[] = [
                'id' => $item['id'],
                'title' => $title,
                'time' => date('Y-m-d', $item['create_time']),
            ];
        }

        return [
            'list' => $list,
        ];
    }

    /**
     * 看过列表
     * @param $user_id
     * @param int $page
     * @return array
     * @throws \Exception
     */
    public function readList($user_id, $page = 1)
    {
        $data = (new MessageUserModel())->field('message_id')->where([
            'user_id' => $user_id,
        ])->order('id desc')->page($page, Config::get('page_size') ?: 20)->select();

        $list = [];
        $MessageModel = new MessageModel();

        foreach ($data as $item) {
            $messageRow = $MessageModel->getMessage($item['message_id']);
            $list[] = [
                'id' => $messageRow['id'],
                'user' => $this->userInfo($messageRow['user_id'], $messageRow['anonymous']),
                'time' => date('Y-m-d', $messageRow['create_time']),
            ];
        }

        return [
            'list' => $list,
        ];
    }

    /**
     * 作者信息
     * @param $user_id
     * @param int $anonymous
     * @return array
     * @throws \Exception
     */
    private function userInfo($user_id, $anonymous = 0)
    {
        if ($anonymous) {
            return [
                'nickname' => '匿名用户',
                'avatar' => get_full_url('/assets/img/avatar.png'),
            ];
        } else {
            $userRow = (new UserService())->getCacheById($user_id);
            return [
                'nickname' => $userRow['nickname'],
                'avatar' => get_full_url($userRow['avatar']),
            ];
        }
    }
}