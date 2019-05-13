<?php

/**
 * 输出JSON
 * @param array $output
 */
function out_json($output = [])
{
    header('Content-type: application/json');
    echo (json_encode($output));
    exit;
}

/**
 * 定义输出的数据结构
 * @param array $data
 * @param int $code
 * @param null $msg
 * @return array
 */
function get_output_contents($data = [], $code = 0, $msg = null)
{
    $code = intval($code);
    $msg = is_null($msg) ? ($code == 0 ? 'OK' : 'Error') : $msg;

    $output = [
        'code' => $code,
        'message' => $msg,
        'data' => $data,
    ];

    if (config('app_debug')) {//调试模式就输出更多数据来看看
        $output['time'] =  request()->server('REQUEST_TIME');

        $debug = [
            'url' => request()->url(true),
            'get' => request()->get(),
            'post' => request()->put() ?: request()->post(),
            'header' => request()->header(),
        ];
        $output['_debug'] = $debug;
    }
    return $output;
}

/**
 * 记下错误日志
 * @param string $message
 * @param array $data
 */
function add_error_log($message = 'error', $data = [])
{
    $log = [
        'url' => request()->url(true),
        'get' => request()->get(),
        'post' => request()->put() ?: request()->post(),
        'header' => request()->header(),
    ];
    $log['message'] = $message;
    $log['data'] = $data;

    trace($log, 'error');
}

/**
 * @param string $string 原文或者密文
 * @param string $operation 操作(ENCODE | DECODE), 默认为 DECODE
 * @param string $key 密钥
 * @param int $expiry 密文有效期, 加密时候有效， 单位 秒，0 为永久有效
 * @return string 处理后的 原文或者 经过 base64_encode 处理后的密文
 *
 * @example
 *
 *  $a = authcode('abc', 'ENCODE', 'key');
 *  $b = authcode($a, 'DECODE', 'key');  // $b(abc)
 *
 *  $a = authcode('abc', 'ENCODE', 'key', 3600);
 *  $b = authcode('abc', 'DECODE', 'key'); // 在一个小时内，$b(abc)，否则 $b 为空
 */
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
{
    $operation = strtoupper($operation);
    $ckey_length = 4;
    // 随机密钥长度 取值 0-32;
    // 加入随机密钥，可以令密文无任何规律，即便是原文和密钥完全相同，加密结果也会每次不同，增大破解难度。
    // 取值越大，密文变动规律越大，密文变化 = 16 的 $ckey_length 次方
    // 当此值为 0 时，则不产生随机密钥

    $key = md5($key);
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);

    $string = $operation == 'DECODE' ? base64_decode(substr(str_replace(array('-','_'),array('+','/'),$string), $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);

    $result = '';
    $box = range(0, 255);

    $rndkey = array();
    for($i = 0; $i <= 255; $i++)
    {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for($j = $i = 0; $i < 256; $i++)
    {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for($a = $j = $i = 0; $i < $string_length; $i++)
    {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if($operation == 'DECODE')
    {
        if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16))
        {
            return substr($result, 26);
        }
        else
        {
            return '';
        }
    }
    else
    {
        return $keyc.str_replace(array('=','+','/'), array('','-','_'), base64_encode($result));
    }
}


//获取资源的URL，不管是本地还是远程的
function get_full_url($path, $default = '/assets/img/avatar.png') {
    $default = $default ?: '/assets/img/avatar.png';
    if (empty($path)) {
        $url = get_local_file_full_url($default);
    } elseif (strstr($path,'://')) {//远程资源
        $url = $path;
    } else {//本地资源
        $url = get_local_file_full_url($path, $default);
    }
    return url_encode($url);
}

//获取本地文件的URL
function get_local_file_full_url($path, $default = '/assets/img/avatar.png')
{
    $default = $default ?: '/assets/img/avatar.png';
    $path = $path ?: $default;

    $path = str_replace('\\','/',$path);
    ltrim($path, '.');
    $path['0'] != '/' AND $path = '/'.$path;

    if (!is_file(".$path")) {
        $path = '/assets/img/avatar.png';
    }

    $Request = \think\Request::instance();
    $url = $Request->domain() . $Request->root() . $path;
    return $url;
}

//完善的urlencode，因为会把:/也转码
function url_encode($string) {
    $entities = array('%3A','%2F');
    $replacements = array(":","/");
    return str_replace($entities, $replacements, urlencode($string));
}
