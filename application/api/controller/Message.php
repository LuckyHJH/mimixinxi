<?php
namespace app\api\controller;

use app\common\controller\Api;
use app\common\service\MessageContentService;
use app\common\service\MessageService;
use think\Config;

class Message extends Api
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 发布信息
     * @throws \Exception
     */
    public function add()
    {
        $content = $this->input('content');
        $rules = $this->input('rules');
        $anonymous = $this->input('anonymous', 'int', 0);
        $this->checkParams($content);
        $this->checkParams($rules);

        $MessageService = new MessageService();
        $row = $MessageService->add($this->user_id, $content, $rules, [
            'anonymous' => $anonymous,
        ]);

        $this->output($MessageService->detail($row['id'], $this->user_id));
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        $file = $this->request->file('image');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }

        $sha1 = $file->hash();

        $upload = Config::get('upload');

        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error(__('Uploaded file format is limited'));
        }
        $replaceArr = [
            '{suffix}'   => $suffix,
            '{.suffix}'  => $suffix ? '.' . $suffix : '',
            '{filesha1}' => $sha1,
        ];
        $savekey = $upload['savekey'];
        $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);
        $savekey = (new MessageContentService())->getUrlByFileName($savekey);//文件名是{sha1}.{suffix}，再通过这个方法获取相对路径

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);
        //
        $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if ($splInfo) {
            $imagewidth = $imageheight = 0;
            if (in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'])) {
                $imgInfo = getimagesize($splInfo->getPathname());
                $imagewidth = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
                $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
            }
            $params = array(
                'admin_id'    => 0,
                'user_id'     => (int)$this->auth->id,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
            );
            $attachment = model("attachment");
            $attachment->data(array_filter($params));
            $attachment->save();
            \think\Hook::listen("upload_after", $attachment);
            $this->success(__('Upload successful'), [
                'url' => $splInfo->getSaveName()
            ]);
        } else {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
    }


    /**
     * 详情页面
     * @param $id
     * @throws \Exception
     */
    public function detail($id)
    {
        $this->checkParams($id);

        $data = (new MessageService())->detail($id, $this->user_id);

        $this->output($data);
    }

    /**
     * 详情内容
     * @param $id
     * @throws \Exception
     */
    public function content($id)
    {
        $this->checkParams($id);

        $data = (new MessageService())->content($id, $this->user_id);

        $this->output($data);
    }

    /**
     * 输出图片
     * @param $message_id
     * @param string $url 图片地址
     * @throws \Exception
     */
    public function image($message_id, $url)
    {
        $this->checkParams([$message_id, $url]);

        $image = (new MessageContentService())->getImageContents($this->user_id, $message_id, $url);

        $suffix = strstr($url, '.');
        $suffix = ltrim($suffix, '.');
        header('Content-type: image/' . $suffix);

        echo $image;
        exit;
    }


    /**
     * 我发布的
     * @throws \Exception
     */
    public function send_list()
    {
        $page = $this->input('page', 'int', 1);

        $data = (new MessageService())->sendList($this->user_id, $page);

        $this->output($data);
    }

    /**
     * 我查看的
     * @throws \Exception
     */
    public function read_list()
    {
        $page = $this->input('page', 'int', 1);

        $data = (new MessageService())->readList($this->user_id, $page);

        $this->output($data);
    }
}
