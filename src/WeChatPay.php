<?php
declare(strict_types=1);

namespace Envern\WeChat;

/**
 * 微信支付
 * @package Envern\WeChat
 * @version V1.0
 */
class WeChatPay
{
    /**
     * JSAPI下单
     * 见：https://pay.weixin.qq.com/docs/merchant/apis/jsapi-payment/direct-jsons/jsapi-prepay.html
     * @param array $config 配置
     * @param array $data 商品
     * @return array
     */
    public static function js_api_order(array $config, array $data): array
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';
        $data = [
            'appid' => $config['wechat_pay_app_id'],//公众号ID
            'mchid' => $config['wechat_pay_mch_id'],//直连商户号
            'notify_url' => $config['wechat_pay_notify_url'],//通知地址
            'out_trade_no' => $data['out_trade_no'],//商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
            'description' => $data['description'],//商品描述
            'amount' => $data['amount'],//订单金额信息
            'payer' => [
                'openid' => ''//用户在普通商户AppID下的唯一标识。 下单前需获取到用户的OpenID
            ],//支付者信息
        ];
        // JSAPI下单
        $result = json_decode(Helper::post_curl(
            $url,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            WeChatPay::create_authorization($config, $url, $data)
        ), true);
        if (empty($result['prepay_id'])) {
            return ['code' => 201, 'msg' => '获取素材列表失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * @param string $xml_data
     * @return array
     */
    public static function wechat_pay_notify(string $xml_data): array
    {
        // TODO 待完善
        // $decrypt_to_string = WeChatPay::decrypt_to_string($config);

        return ['code' => 200, 'msg' => 'success'];
    }

    /**
     * 生成v3 Authorization 获取接口授权header头信息
     * @param array $config
     * @param string $url 请求地址
     * @param array $data 请求参数
     * @param string $method 请求方式
     * @return array
     */
    protected static function create_authorization(array $config, string $url, array $data = [], string $method = 'POST'): array
    {
        // 解析url地址
        $url_parts = parse_url($url);
        //生成签名
        $body = [
            'method' => $method,
            'url' => ($url_parts['path'] . (!empty($url_parts['query']) ? "?{$url_parts['query']}" : '')),
            'time' => time(), // 当前时间戳
            'nonce' => WeChatPay::get_rand_str(32), // 随机32位字符串
            'data' => ((strtolower($method) == 'post' || strtolower($method) == 'patch') ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''), // POST请求时 需要 转JSON字符串
        ];
        $sign = WeChatPay::make_sign($config, $body);
        //Authorization 类型
        $schema = 'WECHATPAY2-SHA256-RSA2048';
        //生成token
        $token = sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $config['wechat_pay_mch_id'],//商户号
            $body['nonce'], $body['time'],
            $config['wechat_pay_cert_serial_number'],// 证书序列号
            $sign
        );
        return [
            'Content-Type:application/json',
            'Accept:application/json',
            'Authorization: ' . $schema . ' ' . $token
        ];
    }

    /**
     * 生成签名
     * @param array $config 配置
     * @param array $data 加密数据
     * @return string
     */
    protected static function make_sign(array $config, array $data): string
    {
        if (!in_array('sha256WithRSAEncryption', \openssl_get_md_methods(true))) {
            throw new \RuntimeException('当前PHP环境不支持SHA256withRSA');
        }

        // 拼接生成签名所需的字符串
        $message = '';
        foreach ($data as $value) {
            $message .= $value . "\n";
        }

        // 获取商户私钥
        $private_key = WeChatPay::get_private_key($config['wechat_pay_key']);
        // 生成签名
        openssl_sign($message, $sign, $private_key, 'sha256WithRSAEncryption');
        return base64_encode($sign);
    }

    /**
     * 获取私钥
     * @param string $filepath 文件地址
     * @return false|\OpenSSLAsymmetricKey
     */
    protected static function get_private_key(string $filepath): \OpenSSLAsymmetricKey|false
    {
        return openssl_pkey_get_private(file_get_contents($filepath));
    }

    /**
     * 随机字符串
     * @param int $length 长度
     * @return string
     */
    protected static function get_rand_str(int $length): string
    {
        $string = 'abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $rand_str = str_shuffle($string);//打乱
        return substr($rand_str, 0, $length);//截取
    }

    /**
     * 证书和回调报文解密
     * @param array $config 配置
     * @param string $associated_data 附加数据包（可能为空）
     * @param string $nonce_str 加密使用的随机串初始化向量
     * @param string $ciphertext Base64编码后的密文
     * @return false|string
     * @throws \SodiumException
     */
    protected static function decrypt_to_string(array $config, string $associated_data, string $nonce_str, string $ciphertext): false|string
    {
        $private_key = $config['api_cert_key'];
        $ciphertext = \base64_decode($ciphertext);
        if (strlen($ciphertext) <= 32) {
            return false;
        }

        // ext-sodium (default installed on >= PHP 7.2)
        if (function_exists('\sodium_crypto_aead_aes256gcm_is_available') &&
            \sodium_crypto_aead_aes256gcm_is_available()) {
            return \sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $associated_data, $nonce_str, $private_key);
        }

        // ext-libsodium (need install libsodium-php 1.x via pecl)
        if (function_exists('\Sodium\crypto_aead_aes256gcm_is_available') &&
            \Sodium\crypto_aead_aes256gcm_is_available()) {
            return \Sodium\crypto_aead_aes256gcm_decrypt($ciphertext, $associated_data, $nonce_str, $private_key);
        }

        // openssl (PHP >= 7.1 support AEAD)
        if (PHP_VERSION_ID >= 70100 && in_array('aes-256-gcm', \openssl_get_cipher_methods())) {
            $ctext = substr($ciphertext, 0, -16);
            $auth_tag = substr($ciphertext, -16);
            return \openssl_decrypt($ctext, 'aes-256-gcm', $private_key, \OPENSSL_RAW_DATA, $nonce_str,
                $auth_tag, $associated_data);
        }

        throw new \RuntimeException('AEAD_AES_256_GCM需要PHP 7.1以上或者安装libsodium-php');
    }
}
