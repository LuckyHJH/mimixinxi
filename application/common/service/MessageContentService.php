<?php
namespace app\common\service;

use app\common\model\MessageContentModel;
use think\Model;

class MessageContentService extends Model
{
    /**
     * 获取内容列表
     * @param $message_id
     * @param $user_id
     * @return array
     * @throws \Exception
     */
    public function getContents($message_id, $user_id)
    {
        $contents = [];
        $list = (new MessageContentModel())->getCacheByMessageId($message_id);
        $auth = \app\api\library\Auth::instance(['expire' => 10]);//10秒过期
        foreach ($list as $item) {
            if ($item['type'] == 'text') {
                $contents[] = [
                    'type' => $item['type'],
                    'data' => $item['data'] ?: '',
                ];
            } elseif ($item['type'] == 'image') {
                $url = $item['data'] ? url('api/message/image', ['message_id' => $message_id, 'url' => $item['data']], false, true) : '';
                if ($url) {
                    $url .= '?token=' . $auth->encryptToken($user_id);
                }
                $contents[] = [
                    'type' => $item['type'],
                    'data' => $url,
                ];
            } else {
                continue;
            }
        }
        return $contents;
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

        $image_contents = file_get_contents($this->getPathByFileName($fileName, '.'));
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
    public function getPathByFileName($fileName, $prefix = '')
    {
        $path = substr($fileName, 0, 2);
        return $prefix . "/uploads/message/{$path}/{$fileName}";
    }

    /**
     * 通过图片名称或路径获取其缩略图的相对路径
     * @param $fileName
     * @param string $prefix
     * @return string
     */
    public function getThumbPathByFileName($fileName, $prefix = '')
    {
        $file_array = explode('/', $fileName);
        $fileName = $file_array[count($file_array) - 1];
        if (empty($fileName)) {
            return '';
        }

        $path = substr($fileName, 0, 2);
        return $prefix . "/uploads/thumb/{$path}/{$fileName}";
    }

    /**
     * 把图片固定转成目标宽高（输入任何像素的图片都会以目标宽高输出图片）
     * @param $file_path
     * @param $ext
     * @param int $width
     * @param int $height
     * @return string
     */
    public function thumbImage($file_path, $ext, $width = 750, $height = 1334)
    {
        $thumb_path = $this->getThumbPathByFileName($file_path, '.');
        if (is_file($thumb_path)) {
            return $thumb_path;
        }

        if (!is_file($file_path)) {
            return '';
        }

        list($source_w, $source_h) = getimagesize($file_path);
        $aspect_ratio = $width / $height;//宽高比
        $src_aspect_ratio = $source_w / $source_h;//宽除以高
        if ($src_aspect_ratio < $aspect_ratio) {
            //想要的更宽，那就宽度达到最大，然后裁剪多出来的高。而不够大也要放大
            $tmp_h = round($width / $src_aspect_ratio);//原图变成目标宽度后的高度
            $diff_rate = $tmp_h / $source_h;//图片拉大或缩小的比例
            $src_x = 0;
            $src_y = round(abs($tmp_h - $height) / 2) / $diff_rate;//裁剪中间的高
            $source_load_h = $height / $diff_rate;//高度变化后，原图加载的高度也相应变化
            $source_load_w = $source_w;
        } elseif ($src_aspect_ratio > $aspect_ratio) {
            //想要的更高，那就高度达到最大，然后裁剪多出来的宽。而不够大也要放大
            $tmp_w = round($height * $src_aspect_ratio);//原图变成目标高度后的宽度
            $diff_rate = $tmp_w / $source_w;//目标图除以原图得到比例
            $src_y = 0;
            $src_x = round(abs($tmp_w - $width) / 2) / $diff_rate;//裁剪中间的宽
            $source_load_w = $width / $diff_rate;//只加载中间的宽
            $source_load_h = $source_h;
        } else {
            //比例一样，那就原图全部加载
            $src_x = 0;
            $src_y = 0;
            $source_load_w = $source_w;
            $source_load_h = $source_h;
        }
        $dst_w = $width;
        $dst_h = $height;

        $dst_image = imagecreatetruecolor($dst_w, $dst_h);
        $create_fun = 'imagecreatefrom'.$ext;
        if (!function_exists($create_fun)) {
            return '';
        }
        $src_image = $create_fun($file_path);
        imagesavealpha($src_image, true);
        imagealphablending($dst_image, false);
        imagesavealpha($dst_image, true);
        //以上3个函数在处理透明图片时会用到
        imagecopyresampled($dst_image, $src_image, 0, 0, $src_x, $src_y,
            $dst_w, $dst_h, $source_load_w, $source_load_h);

        $thumb_dir = dirname($thumb_path);
        if (!(is_dir($thumb_dir) || mkdir($thumb_dir, 0755, true))) {
            return '';
        }

        $func = 'image'.$ext;
        if (!function_exists($func)) {
            return '';
        }
        $func($dst_image, $thumb_path);
        imagedestroy($src_image);
        imagedestroy($dst_image);
        return $thumb_path;
    }

    /**
     * 获取信息的标题的缓存
     * @param $message_id
     * @return string
     * @throws \Exception
     */
    public function getTitleCache($message_id)
    {
        $cache_key = 'message_title_' . $message_id;
        $data = cache($cache_key);
        if (empty($data)) {
            $data = $this->getTitle($message_id);
            $data and cache($cache_key, $data);
        }
        return $data;
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

        if ($row) {
            $text = mb_strcut(strval($row['data']), 0, 30, 'utf-8');//截取10个中文
        } else {
            //没有的话就算图片数量
            $count = $MessageContentModel->field('data')->where([
                'message_id' => $message_id,
                'type' => 2,
                'is_del' => 0,
            ])->order('id asc')->count();

            if ($count) {
                $text = "[{$count}张图片]";
            } else {
                $text = '';
            }
        }

        return $text;
    }
}