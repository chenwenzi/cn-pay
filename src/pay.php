<?php
/**
 * php cn payment
 *
 *
 * @category Class
 * @package  PaymentService
 * @author   wenzi <chenwenzi@outlook.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/chenwezi/php-third-pay
 */
class PaymentService
{
    const PAYMENT = 'cnPay';

    private $_pay_config;
    private $_pay_params;
    private $_pay_params_ignore = [];
    private $_pay_api;
    private $_sign;
    private $_sign_type;
    private $_sign_list = [ //support sign type
        'md5',
    ];
    private $_sign_key;
    private $_sign_key_name;
    private $_sign_cert;
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
     * @param string $sign     sign type
     */
    public function __construct(array $payConfig, array $payParams)
    {
        $this->_pay_config = $payConfig;
        $this->_pay_params = $payParams;
        $this->_log = (bool)($payConfig['log'] ?? $payConfig['debug'] ?? 0);
        $this->_log_pretty = (bool)($payConfig['log_pretty'] ?? 0);
        $this->_log_file = $payConfig['log_file'] ?? null;
        $this->_method = strtoupper($payConfig['method'] ?? 'POST');
        $this->_sign = $payConfig['signName'] ?? '';
        $this->_input = $payConfig['input'] ?? [];
        $this->_pay_params_ignore = $payConfig['exclude'] ?? [];

        //handle exception
        set_exception_handler(function (\Throwable $e) {
            $this->log($e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            throw $e;
        });
        set_error_handler(function (\Throwable $e) {
            $this->log($e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            throw $e;
        });

        $this->_sign_type = array_combine($this->_sign_list, $this->_sign_list)[strtolower($payConfig['signType'] ?? 'md5')];
        if(!$this->_sign_type) {
            throw new \Exception('unsupported sign type, supported: '. join(', ', $this->_sign_list));
        }
        if($this->_sign_type == 'md5') {
            $this->_sign_key = $payConfig['signKey'] ?? '';
            $this->_sign_key_name = $payConfig['signKeyName'] ?? 'key';
            if(!$this->_sign_key) {
                throw new \Exception('can\'t get "signKey" in payConfig, when sign type: md5');
            }
        }

        if (!$this->_sign) {
            throw new \Exception('can\'t get "signName" in payConfig');
        }

        $this->_pay_api = $this->_pay_config['payApi'] ?? '';
        if(!$this->_pay_api) {
            throw new \Exception('can\'t get "payApi" in payConfig');
        }

        $this->_client = new GuzzleHttp\Client();
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
        $this->_pay_params_ignore = array_unique(array_merge($this->_pay_params_ignore, $keys));
    }

    private function _generateSignature() : void
    {
        if($this->_sign_type != 'md5') {
            throw new \Exception('supported sign type only: md5');
        }
        $buff = '';
        $data = $this->_pay_params;
        $exclude = $this->_pay_params_ignore;
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
        $buff .= ($this->_sign_key_name . '=' . $this->_sign_key);
        $this->log($buff, 'signature string');
        $sign = strtoupper(md5($buff));
        $this->log($sign, 'signature result');
        $this->_pay_params[$this->_sign] = $sign;
        $this->_addPayParamsIgnore($this->_sign);
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
        $this->log($this->_ret, 'Pay response data');
    }

    public function verify() : bool
    {
        if(empty($this->_input)) {
            $this->_input = $_REQUEST;
        }
        $this->log($this->_input, 'input data');
        if(empty($this->_input[$this->_sign])) {
            throw new \Exception("no sign key find: ['{$this->_sign}']");
        }
        $this->_pay_params = $this->_input;
        $this->_generateSignature();
        $this->_buildPayParameters();
        $this->log(strtoupper($this->_input[$this->_sign]), 'input-signature');

        return $this->_pay_params[$this->_sign] === strtoupper($this->_input[$this->_sign]);
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
        if(is_array($msg)) {
            if($this->_log_pretty) {
                $msg = print_r($msg, true);
            }
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        $this->_log_file = $this->_log_file ?? 'log-' . date('Y-m-d') . '.txt';
        $head = self::PAYMENT. ' '.date('Y-m-d H:i:s ').'('.$title.') '.($_SERVER['REQUEST_URI'] ?? '').PHP_EOL;
        @file_put_contents($dir . '/' . $this->_log_file, $head . $msg . PHP_EOL . PHP_EOL, FILE_APPEND);
    }
}