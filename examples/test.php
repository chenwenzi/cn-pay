<?php

require_once __DIR__ . '/../vendor/autoload.php';
error_reporting(E_ALL);

$pay = new PaymentService(
    [
        'debug' => 1,    //打印日志
        'signKey' => 123,  //md5 加密秘钥
        'payApi' => 'https://comics.mkd22448.com/index.php/appv1/kxapps/kp', //支付api
        //'input' => json_decode('{"amount":100,"orderNo":"2023081217351364d752","subject":"支付100元","sign":"B84B243F0892890914C5F8F362158D87"}', true),
        'exclude' => [
            //参与请求参数，但不参与签名 或者 验签是不参与签名的字段
            'subject',
            'sign',
        ]
    ], [
        'subject' => '支付100元',
        'amount' => 100, // 参与请求参数与签名
        'orderNo' => date('YmdHis') . substr(uniqid(),0,6),
    ]
);

//$pay->send(); // return result

//amount=100&orderNo=2023081217351364d752&k
try {
    var_dump($pay->verify());
} catch (\Exception $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
}
