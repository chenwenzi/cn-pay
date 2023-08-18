<?php
/**
 * php cn payment
 *
 *
 * @category Class
 * @package  cnPayment
 * @author   wenzi <chenwenzi@outlook.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/chenwezi/cn-pay
 */

namespace chenwenzi;

use GuzzleHttp\Client;

class cnPayment
{
    const PAYMENT = 'cnPay';

    private $_pay_config;
    private $_pay_params;
    private $_encrypt_exclude = [];
    private $_pay_api;
    private $_encrypt_sign;
    private $_encrypt_type;
    private $_encrypt_list = [ //support sign type
        'md5',
    ];
    private $_encrypt_secret;
    private $_encrypt_key;
    private $_encrypt_cert;
    private $_ret;

    private $_log = 0; //log
    private $_log_pretty;
    private $_log_file;

    private $_method;
    private $_body;
    private $_headers;
    private $_input;
    private $_client;


    /**
     *
     * @param array $payConfig  payment secret key and ...
     * @param array $payParams $pay params
     */
    public function __construct(array $payConfig, array $payParams)
    {
        $this->_pay_config = $payConfig;
        $this->_pay_params = $payParams;
        $this->_log = (bool)($payConfig['log'] ?? $payConfig['debug'] ?? 0);
        $this->_log_pretty = (bool)($payConfig['log_pretty'] ?? 0);
        $this->_log_file = $payConfig['log_file'] ?? null;
        $this->_method = strtoupper($payConfig['method'] ?? 'POST');
        $this->_encrypt_sign = $payConfig['encrypt']['sign'] ?? 'sign';
        $this->_input = $payConfig['input'] ?? [];
        $this->_encrypt_exclude = $payConfig['encrypt']['exclude'] ?? [];

        //handle exception
        set_exception_handler(function (\Throwable $e) {
            $this->log($e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            throw $e;
        });
        set_error_handler(function (\Throwable $e) {
            $this->log($e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            throw $e;
        });

        $this->_encrypt_type = array_combine($this->_encrypt_list, $this->_encrypt_list)[strtolower($payConfig['encrypt']['type'] ?? 'md5')];
        if(!$this->_encrypt_type) {
            throw new \Exception('unsupported sign type, supported: '. join(', ', $this->_encrypt_list));
        }
        if($this->_encrypt_type == 'md5') {
            $this->_encrypt_secret = $payConfig['encrypt']['secret'] ?? '';
            $this->_encrypt_key = $payConfig['encrypt']['key'] ?? 'key';
            if(!$this->_encrypt_secret) {
                throw new \Exception('can\'t get "encrypt[key]" in payConfig, when sign type: md5');
            }
        }

        if (!$this->_encrypt_sign) {
            throw new \Exception('can\'t get "encrypt[sign]" in payConfig');
        }

        $this->_pay_api = $this->_pay_config['api'] ?? '';
        if(empty($this->_pay_api) or strlen($this->_pay_api) < 5) {
            throw new \Exception('can\'t get "api" in payConfig');
        }

        $this->_client = new Client();
    }

    public function send()
    {
        $this->_request();
        if (($this->_ret['code'] ?? 0) == 200) {
           return $this->_ret['body'] ?? [];
        }
        throw new \Exception($this->_ret['body'] ?? []);
    }

    private function _addPayParamsIgnore($keys) : void
    {
       !is_array($keys) and $keys = [$keys];
        $this->_encrypt_exclude = array_unique(array_merge($this->_encrypt_exclude, $keys));
    }

    private function _generateSignature() : void
    {
        if($this->_encrypt_type != 'md5') {
            throw new \Exception('supported sign type only: md5');
        }
        $buff = '';
        $data = $this->_pay_params;
        $exclude = $this->_encrypt_exclude;
        ksort($data);
        $this->log($data, 'signature data');
        foreach ($data as $k => $v) {
            $k = trim($k);
            if(in_array($k, $exclude)) {
                continue;
            }
            if($v == '') {
                continue;
            }
            $buff .= ($k . '=' . $v . '&');
        }
        $buff .= ($this->_encrypt_key . '=' . $this->_encrypt_secret);
        $this->log($buff, 'signature string');
        $sign = ($this->_pay_config['encrypt']['upper'] ?? false) ? strtoupper(md5($buff)) : md5($buff);
        $this->log($sign, 'signature result');
        $this->_pay_params[$this->_encrypt_sign] = $sign;
        $this->_addPayParamsIgnore($this->_encrypt_sign);
    }

    private function _buildPayParameters()
    {
        if ($this->_method == 'JSON') {
            $this->_body = $this->_pay_params;
        } elseif ($this->_method == 'GET') {
            $this->_pay_api .= '?' . http_build_query($this->_pay_params);
        }
    }

    /**
     * Request handler.
     *
     */
    private function _request() : void
    {
        $options = [
            'headers' => $this->_headers ?? [],
            'http_errors' => false
        ];

        $this->_generateSignature();
        $this->_buildPayParameters();
        //log
        $this->log($this->_pay_params, 'Pay request params');
        $this->log($this->_pay_api, 'Pay request api, '. $this->_method);

        //data
        $this->_method == 'POST' and $options['form_params'] = $this->_pay_params;
        $this->_method == 'JSON' and $options['body'] = $this->_body ?? '' and $this->_method = 'POST';

        //request
        $ret = $this->_client->request(
            $this->_method,
            $this->_pay_api,
            $options
        );
        //result
        $this->_ret = [
            'code' => $ret->getStatusCode(),
            'body' => $ret->getBody()->getContents()
        ];
        $this->log($this->_ret['code'], 'Pay response code');
        $this->log($this->_ret['body'], 'Pay response body');
    }

    public function verify() : bool
    {
        if(empty($this->_input)) {
            $this->_input = $_REQUEST;
        }
        $this->log($this->_input, 'input data');
        if(empty($this->_input[$this->_encrypt_sign])) {
            $this->log("no sign key find: [{$this->_encrypt_sign}]");
            return false;
        }
        $this->_pay_params = $this->_input;
        $this->_generateSignature();
        $this->log(strtoupper($this->_input[$this->_encrypt_sign]), 'input-signature');

        $sign = $this->_pay_params[$this->_encrypt_sign];
        if(strtoupper($sign) !== strtoupper($this->_input[$this->_encrypt_sign])) {
            $this->log('verify sign failed');
            return false;
        }
        $this->log('verify sign success');

        return true;
    }

    private function isJson($string) : bool
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    private function log($msg = '', $title = '') {
        if(!$this->_log) {
            return;
        }
        $dir = self::PAYMENT;
        if(!is_dir($dir)) {
            mkdir($dir, 7555);
        }
        if(empty($msg)) {
            $msg = json_encode($msg);
        }
        if(is_array($msg) or is_object($msg)) {
            if($this->_log_pretty) {
                $msg = print_r($msg, true);
            }
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        } else {
            if($this->_log_pretty and $this->isJson($msg)) {
                $msg = print_r(json_decode($msg, true), true);
            }
        }
        $debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $title = ($debug[1]['function'] ?? '') . ': ' . $title;
        $this->_log_file = $this->_log_file ?? 'log-' . date('Y-m-d') . '.txt';
        $head = self::PAYMENT. ' '.date('Y-m-d H:i:s ').'('.$title.') '.($_SERVER['REQUEST_URI'] ?? '').PHP_EOL;
        @file_put_contents($dir . '/' . $this->_log_file, $head . $msg . PHP_EOL . PHP_EOL, FILE_APPEND);
    }
}