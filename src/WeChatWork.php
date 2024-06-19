<?php
declare(strict_types=1);

namespace Envern\WeChat;

use Illuminate\Support\Facades\Cache;

/**
 * 企业微信
 * @package Envern\WeChat
 * @version V1.0
 */
class WeChatWork
{
    /**
     * 用户同意授权，获取code 构造网页授权链接
     * 见 https://developer.work.weixin.qq.com/document/path/91120
     * @param array $config 企业微信配置
     * @param string $auth_url 授权页面
     * @param string|null $expand 扩展参数，格式序列化加密
     * @return string 返回请求地址
     */
    public static function authorize(array $config, string $auth_url, string|null $expand = ''): string
    {
        // 扩展参数组装
        $extend_data = [
            'after_url' => $auth_url,
            'expand' => $expand
        ];
        // 授权回调地址
        $redirect_uri = urlencode(str_replace('authorize', 'auth_call_back', url()->current()) . '?' . http_build_query($extend_data));
        // 组装请求参数
        $param_data = [
            'appid' => $config['work_corp_id'],
            'agentid' => $config['work_agent_id'],
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'snsapi_privateinfo',
            'state' => 'STATE',
        ];
        // 返回请求地址
        return 'https://open.weixin.qq.com/connect/oauth2/authorize' . http_build_query($param_data) . '#wechat_redirect';
    }

    /**
     * 用户同意授权，获取用户信息
     * 见 https://developer.work.weixin.qq.com/document/path/91023
     * @param array $config 企业微信配置
     * @param string $code 授权后的cede
     * @return array
     */
    public static function auth_call_back(array $config, string $code): array
    {
        // 获取 access token
        $access_token = WeChatWork::access_token($config);
        if ($access_token['code'] != 200) {
            return $access_token;
        }
        // 获取用户信息
        $result = json_decode(Helper::get_curl('https://qyapi.weixin.qq.com/cgi-bin/auth/getuserinfo', [
            'access_token' => $access_token['data']['access_token'],
            'code' => $code,
        ]), true);
        if (!empty($result['errcode']) || empty($result['openid'])) {
            return ['code' => 201, 'msg' => '获取用户信息失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 手机号获取userid
     * 见 https://developer.work.weixin.qq.com/document/path/91693
     * @param array $config 企业微信配置
     * @param string|int $mobile 手机号
     * @return array
     */
    public static function mobile_user_id(array $config, string|int $mobile): array
    {
        // 获取 access token
        $access_token = WeChatWork::access_token($config);
        if ($access_token['code'] != 200) {
            return $access_token;
        }
        // 手机号获取userid
        $result = json_decode(Helper::post_curl(
            "https://qyapi.weixin.qq.com/cgi-bin/user/getuserid?access_token={$access_token['data']['access_token']}",
            json_encode(['mobile' => $mobile], JSON_UNESCAPED_UNICODE)
        ), true);
        if (!empty($result['errcode']) || empty($result['userid'])) {
            return ['code' => 201, 'msg' => '获取userid失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 获取 access_token
     * @param array $config 企业微信配置
     * 见 https://developer.work.weixin.qq.com/document/path/91039
     * @return array
     */
    protected static function access_token(array $config): array
    {
        // 获取本地token
        $access_token = Cache::get('work_access_token');
        if (!empty($access_token)) {
            return ['code' => 200, 'msg' => '获取 Access Token 成功.', 'data' => json_decode($access_token, true)];
        }
        $result = json_decode(Helper::get_curl('https://qyapi.weixin.qq.com/cgi-bin/gettoken', [
            'corpid' => $config['work_corp_id'],
            'corpsecret' => $config['work_corp_secret'],
        ]), true);
        if (!empty($result['errcode']) || empty($result['access_token'])) {
            return ['code' => 201, 'msg' => '获取AccessToken失败.'];
        }
        // 设置token，过期时间1.9小时
        $access_token = Cache::put('work_access_token', json_encode($result, JSON_UNESCAPED_UNICODE), 114);
        if (empty($access_token)) {
            return ['code' => 201, 'msg' => 'Access Token 设置失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }
}
