<?php
declare(strict_types=1);

namespace Envern\WeChat;

use Illuminate\Support\Facades\Cache;

/**
 * 小程序
 * @package Envern\WeChat
 * @version V1.0
 */
class WeChatMini
{
    /**
     * 获取用户信息
     * @param array $config 小程序配置
     * @param string $js_code
     * @param string $encrypted_data 加密的用户数据
     * @param string $iv 与用户数据一同返回的初始向量
     * @return array
     */
    public static function user_info(array $config, string $js_code, string $encrypted_data, string $iv): array
    {
        // 小程序登录 获取 session_key 会话密钥
        $code2_session = WeChatMini::code2_session($config, $js_code);
        if ($code2_session['code'] != 200) {
            return ['code' => 201, 'msg' => $code2_session['msg']];
        }
        // 返回解密数据
        $decrypt_data = WeChatMini::decrypt_data($config['mini_app_id'], $code2_session['data']['session_key'], $encrypted_data, $iv);
        if ($decrypt_data['code'] != 200) {
            return ['code' => 201, 'msg' => $decrypt_data['msg']];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $decrypt_data['data']];
    }

    /**
     * 小程序登录
     * 文档 https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/user-login/code2Session.html
     * @param array $config 配置
     * @param string $js_code 登录时获取的 code，可通过wx.login获取
     * @return array
     */
    public static function code2_session(array $config, string $js_code): array
    {
        $result = json_decode(Helper::get_curl('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $config['mini_app_id'],//小程序 appId
            'secret' => $config['mini_app_secret'],//小程序 appSecret
            'js_code' => $js_code,//登录时获取的 code，可通过wx.login获取
            'grant_type' => 'authorization_code',//授权类型，此处只需填写 authorization_code
        ]), true);
        if (empty($result['openid']) || !empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '授权登录失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 获取手机号
     * 见：https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/user-info/phone-number/getPhoneNumber.html
     * @param array $config 配置
     * @param string $js_code 手机号获取凭证
     * @return array
     */
    public static function user_phone_number(array $config, string $js_code): array
    {
        // 获取接口调用凭据
        $access_token = WeChatMini::access_token($config);
        if ($access_token['code'] != 200) {
            return ['code' => 201, 'msg' => $access_token['msg']];
        }
        // 获取手机号码
        $result = json_decode(Helper::post_curl(
            "https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token={$access_token['data']['access_token']}",
            json_encode(['code' => $js_code], JSON_UNESCAPED_UNICODE)
        ), true);
        if (empty($result['phone_info']) || !empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '授权获取手机号失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 获取接口调用凭据
     * 文档 https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/mp-access-token/getAccessToken.html
     * @param array $config 配置
     * @return array
     */
    protected static function access_token(array $config): array
    {
        // 获取本地token
        $access_token = Cache::get('mini_access_token');
        if (!empty($access_token)) {
            return ['code' => 200, 'msg' => 'success', 'data' => json_decode($access_token, true)];
        }
        $result = json_decode(Helper::get_curl('https://api.weixin.qq.com/cgi-bin/token', [
            'appid' => $config['mini_app_id'],//小程序唯一凭证，即 AppID
            'secret' => $config['mini_app_secret'],//小程序唯一凭证密钥，即 AppSecret
            'grant_type' => 'client_credential',//填写 client_credential
        ]), true);
        if (empty($result['access_token']) || !empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '获取 Access Token 失败.'];
        }
        // 设置token，过期时间1.9小时
        $access_token = Cache::put('mini_access_token', json_encode($result, JSON_UNESCAPED_UNICODE), 114);
        if (empty($access_token)) {
            return ['code' => 201, 'msg' => 'Access Token 设置失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * 文档 https://developers.weixin.qq.com/miniprogram/dev/framework/open-ability/signature.html#%E5%8A%A0%E5%AF%86%E6%95%B0%E6%8D%AE%E8%A7%A3%E5%AF%86%E7%AE%97%E6%B3%95
     * @param string $session_key jscode2session接口获取的 会话密钥
     * @param string $encrypted_data 加密的用户数据
     * @param string $iv 与用户数据一同返回的初始向量
     * @return array 失败返回对应的错误码，
     */
    protected static function decrypt_data(string $mini_app_id, string $session_key, string $encrypted_data, string $iv): array
    {
        if (strlen($session_key) != 24) {
            return ['code' => 201, 'msg' => '会话密钥错误.'];
        }
        $aesKey = base64_decode($session_key);
        if (strlen($iv) != 24) {
            return ['code' => 201, 'msg' => '初始向量错误.'];
        }
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encrypted_data);
        $result = json_decode(openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV), true);
        if (empty($result)) {
            return ['code' => 201, 'msg' => '校验失败.'];
        }
        if ($result['watermark']['appid'] != $mini_app_id) {
            return ['code' => 201, 'msg' => '校验失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }
}
