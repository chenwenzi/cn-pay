<?php

require_once __DIR__ . '/../vendor/autoload.php';
error_reporting(E_ALL);

$pay = new PaymentService(
    [
        'debug' => 1,    //打印日志
        'signKey' => 'sx123',  //md5 加密秘钥
        'payApi' => 'http://cn-pay.com/examples/api.php', //支付api
        //'input' => json_decode('{"amount":100,"orderNo":"2023081217351364d752","subject":"支付100元","sign":"B84B243F0892890914C5F8F362158D87"}', true),
        'signName' => 'pay_md5sign', //签名字段名
        'method' => 'POST',
        'exclude' => [
            //参与请求参数，但不参与签名 或者 验签是不参与签名的字段
            'pay_md5sign',
        ]
    ], [
        // 参与请求参数与签名
        'pay_memberid' => '230904425',
        'pay_orderid' => date('YmdHis') . substr(uniqid(),0,6),
        'pay_applydate' => date('Y-m-d H:i:s'),
        'pay_bankcode' => 961,
        'pay_notifyurl' => 'https://www.google.com',
        'pay_callbackurl' => 'https://www.google.com',
        'pay_amount' => 100,
    ]
);

try {
    $ret = $pay->send(); //request Pay
    echo('<pre>');
    print_r($ret);
} catch (\Exception $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
}

//amount=100&orderNo=2023081217351364d752&k
try {
    //var_dump($pay->verify());
} catch (\Exception $e) {
    echo $e->getMessage();
    echo $e->getTraceAsString();
}
