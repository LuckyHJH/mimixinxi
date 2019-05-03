<?php

//配置文件
return [
    'exception_handle'        => '\\app\\api\\library\\ExceptionHandle',

    'token'                  => [
        // 密钥
        'key'      => \think\Env::get('encrypt_key'),
        // token有效期 0表示永久缓存
        'expire'   => 0,
    ],

    'page_size'  => 20,

    'upload' => [
        /**
         * 上传地址,默认是本地上传
         */
        'uploadurl' => 'message/upload',
        /**
         * 文件保存格式
         */
        'savekey'   => '{filesha1}{.suffix}',
        /**
         * 最大可上传大小
         */
        'maxsize'   => '10mb',
        /**
         * 可上传的文件类型
         */
        'mimetype'  => 'jpg,png,bmp,jpeg,gif',
        /**
         * 是否支持批量上传
         */
        'multiple'  => false,
    ],

    'cache' => [
        // 驱动方式
        'type'   => 'File',
        // 缓存保存目录
        'path'   => CACHE_PATH,
        // 缓存前缀
        'prefix' => 'api',
        // 缓存有效期 0表示永久缓存
        'expire' => 86400,
    ],
];
