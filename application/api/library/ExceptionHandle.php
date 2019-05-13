<?php

namespace app\api\library;

use Exception;
use think\exception\Handle;

/**
 * 自定义API模块的错误显示
 */
class ExceptionHandle extends Handle
{

    public function render(Exception $e)
    {
        $code = $e->getCode();
        empty($code) and $code = 500;

        $msg = $e->getMessage();
        empty($msg) and $msg = '服务器错误，请稍后重试';

        $output = get_output_contents([], $code, $msg);
        if (config('app_debug')) {//调试模式就直接输出来看看
            $output['_debug']['exception'] = $e->getTrace();
        }

        //错误日志（所有错误都记下来）
        add_error_log($msg, [
            'code' => $code,
            'trace' => $e->getTrace(),
        ]);

        // 验证异常
        if ($e instanceof \think\exception\ValidateException)
        {
            $output['code'] = 400;
            $output['message'] = $e->getError();
            return json($output);
        }

        // Http异常
        if ($e instanceof \think\exception\HttpException)
        {
            $output['code'] = $e->getStatusCode();
            return json($output);
        }

        // 4XX客户端错误
        if ($code >= 400 && $code < 500)
        {
            $output['code'] = $code;
            $output['message'] = $msg;
            return json($output);
        }

        // 剩下的就是系统级错误
        if (!config('app_debug'))
        {
            //生产环境的msg内容不能输出给用户看
            $output['message'] = '服务器错误，请稍后重试';
        }
        return json($output);
    }

}
