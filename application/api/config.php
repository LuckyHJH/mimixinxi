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
];
