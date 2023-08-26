<?php

use chenwenzi\cnPayment;

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);

$payConfig = [
    'debug' => 1,    //打印日志
    'api' => 'http://localhost/examples/api.php', //支付api
    'queryApi' => 'http://cn-pay.com/examples/api.php', //查询api
    //verify sign demo input
    //'input' => json_decode('{"return_code":"SUCCESS","return_msg":"OK","trade_no":"536052","out_trade_no":"20230815112022295547","amount":"100.00","create_time":"1692069624","expire_time":"","subject":"￥100","client_ip":"","sign":"217D0FA9A734A7A31F0FB9C9CA44671D"}', true),
    'method' => 'POST',  // default: POST
    'encrypt' => [ //加密配置
        //'type' => 'md5', //签名方式 default: md5
        //'upper' => true, //使用大写签名 default: true
        //'sign' => 'sign', //签名字段名 &sign=md5(...) default: sign
        //'key' => 'key', //签名拼接字段名   md5(....&key=secret) default: key
        'secret' => '123', //加密秘钥
        'exclude' => [ //参与请求参数，但不参与签名 或者 验签是不参与签名的字段
            'sign',
        ]
    ],

];

$payParams = [
    // 参与请求参数与签名
    'app_id' => '1653',
    'out_trade_no' => date('YmdHis') . mt_rand(100000, 999999),
    'subject' => '￥100',
    'amount' => 100,
    'channel' => 601,
    'client_ip' => $_SERVER['REMOTE_ADDR'],
    'return_url' => 'https://www.google.com',
    'notify_url' => 'https://www.google.com',
];

try {
    $pay = new cnPayment($payConfig, $payParams);
} catch (\Throwable $e) {
    echo $e->getMessage();
    die($e->getTraceAsString());
}

try {
    $ret = $pay->send(); //request Pay
    //echo('<pre>');
    //print_r($ret);
} catch (\Throwable $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
}

try {
    //$ret = $pay->query(['no' => 123]); //query Pay
    //echo('<pre>');
    //print_r($ret);
} catch (\Throwable $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
}

try {
    //var_dump($pay->verify()); //verify signature
} catch (\Throwable $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
}
