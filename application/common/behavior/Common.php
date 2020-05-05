<?php

namespace app\common\behavior;

use think\Config;
use think\Lang;
use think\Loader;

class Common
{

    public function moduleInit(&$request)
    {
        // 设置mbstring字符编码
        mb_internal_encoding("UTF-8");

        // 如果修改了index.php入口地址，则需要手动修改cdnurl的值
        $url = preg_replace("/\/(\w+)\.php$/i", '', $request->root());
        // 如果未设置__CDN__则自动匹配得出
        if (!Config::get('view_replace_str.__CDN__'))
        {
            Config::set('view_replace_str.__CDN__', $url);
        }
        // 如果未设置__PUBLIC__则自动匹配得出
        if (!Config::get('view_replace_str.__PUBLIC__'))
        {
            Config::set('view_replace_str.__PUBLIC__', $url . '/');
        }
        // 如果未设置__ROOT__则自动匹配得出
        if (!Config::get('view_replace_str.__ROOT__'))
        {
            Config::set('view_replace_str.__ROOT__', preg_replace("/\/public\/$/", '', $url . '/'));
        }
        // 如果未设置cdnurl则自动匹配得出
        if (!Config::get('site.cdnurl'))
        {
            Config::set('site.cdnurl', $url);
        }
        // 如果未设置cdnurl则自动匹配得出
        if (!Config::get('upload.cdnurl'))
        {
            Config::set('upload.cdnurl', $url);
        }
        if (Config::get('app_debug'))
        {
            // 如果是调试模式将version置为当前的时间戳可避免缓存
            Config::set('site.version', time());
            // 如果是开发模式那么将异常模板修改成官方的
            Config::set('exception_tmpl', THINK_PATH . 'tpl' . DS . 'think_exception.tpl');
        }
        // 如果是trace模式且Ajax的情况下关闭trace
        if (Config::get('app_trace') && $request->isAjax())
        {
            Config::set('app_trace', false);
        }
        // 切换多语言
        if (Config::get('lang_switch_on') && $request->get('lang'))
        {
            \think\Cookie::set('think_var', $request->get('lang'));
        }
        // Form别名
        if (!class_exists('Form')) {
            class_alias('fast\\Form', 'Form');
        }
    }

    public function addonBegin(&$request)
    {
        // 加载插件语言包
        Lang::load([
            APP_PATH . 'common' . DS . 'lang' . DS . $request->langset() . DS . 'addon' . EXT,
        ]);
        $this->moduleInit($request);
    }

    /**
     * @param \app\common\model\Attachment $attachment
     */
    public function uploadAfter($attachment)
    {
        $data = $attachment->getData();
        //$data包含以下内容
        //"user_id": 1,
        //"filesize": 331,
        //"imagewidth": 148,
        //"imageheight": 148,
        //"imagetype": "png",
        //"mimetype": "image/png",
        //"url": "/uploads/message/af/af3226c02f2f68b68fe72c3e7171a4dc490edd19.png",
        //"uploadtime": 1586585387,
        //"storage": "local",
        //"sha1": "af3226c02f2f68b68fe72c3e7171a4dc490edd19",
        //"createtime": 1586585387,
        //"updatetime": 1586585387,
        //"id": "17"

        //图片类型内容，安全检测（像素不超过750x1334）
        $thumb_path = (new \app\common\service\MessageContentService())->thumbImage(".{$data['url']}", $data['imagetype']);

        $mp = new \MiniProgram();
        $SecCheck = new \MiniProgram\Api\SecCheck($mp);

        try {
            $isRisky = $SecCheck->imageIsRisky($thumb_path);
            if ($isRisky) {
                notice('发布风险内容', '图片内容', $data['user_id'], $data['url']);
            }
        } catch (\MiniProgram\ApiException $apiException) {
            add_error_log('imageIsRisky failed', $apiException);
        }
    }

}
