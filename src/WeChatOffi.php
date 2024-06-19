<?php
declare(strict_types=1);

namespace Envern\WeChat;

use Illuminate\Support\Facades\Cache;

/**
 * 公众号
 * @package Envern\WeChat
 * @version V1.0
 */
class WeChatOffi
{
    /**
     * 第一步：用户同意授权，获取code
     * 见 https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#0
     * @param array $config 微信公众号配置
     * @param string $redirect_uri 授权页面
     * @return string 返回请求地址
     */
    public static function authorize(array $config, string $redirect_uri): string
    {
        // 组装请求参数
        $param_data = [
            'appid' => $config['offi_app_id'],// 公众号的唯一标识
            'redirect_uri' => urlencode($redirect_uri),// 授权后重定向的回调链接地址， 请使用 urlEncode 对链接进行处理
            'response_type' => 'code',// 返回类型，请填写code
            'scope' => 'snsapi_userinfo',// 应用授权作用域，snsapi_base （不弹出授权页面，直接跳转，只能获取用户openid），snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、性别、所在地。并且， 即使在未关注的情况下，只要用户授权，也能获取其信息 ）
            'state' => 'STATE',// 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值，最多128字节
        ];
        // 返回请求地址
        return 'https://open.weixin.qq.com/connect/oauth2/authorize' . http_build_query($param_data) . '#wechat_redirect';
    }

    /**
     * 第二步：通过code换取网页授权access_token
     * 见：https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#1
     * @param array $config 微信公众号配置
     * @param string $code 第一步获取的code参数
     * @return array
     */
    protected static function auth_access_token(array $config, string $code): array
    {
        $result = json_decode(Helper::get_curl('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'appid' => $config['offi_app_id'],//公众号的唯一标识
            'secret' => $config['offi_app_secret'],//公众号的appsecret
            'code' => $code,//填写第一步获取的code参数
            'grant_type' => 'authorization_code',//填写为authorization_code
        ]), true);
        if (empty($result['access_token']) || !empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '获取 access Token 失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 第三步：刷新access_token（如果需要）
     * 见：https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#2
     * @param array $config 微信公众号配置
     * @param string $refresh_token 通过access_token获取到的refresh_token参数
     * @return array
     */
    protected static function auth_refresh_token(array $config, string $refresh_token): array
    {
        $result = json_decode(Helper::get_curl('https://api.weixin.qq.com/sns/oauth2/refresh_token', [
            'appid' => $config['offi_app_id'],//公众号的唯一标识
            'grant_type' => 'refresh_token',//填写为refresh_token
            'refresh_token' => $refresh_token,//填写通过access_token获取到的refresh_token参数
        ]), true);
        if (!empty($result['errcode']) || empty($result['access_token'])) {
            return ['code' => 201, 'msg' => '刷新 access token 失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 第四步：拉取用户信息(需scope为 snsapi_userinfo)
     * 见：https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#3
     * @param string $access_token 网页授权接口调用凭证
     * @param string $openid 用户的唯一标识
     * @return array
     */
    protected static function auth_user_info(string $access_token, string $openid): array
    {
        $result = json_decode(Helper::get_curl('https://api.weixin.qq.com/sns/oauth2/refresh_token', [
            'access_token' => $access_token,//网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
            'openid' => $openid,//用户的唯一标识
            'lang' => 'zh_CN',//返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
        ]), true);
        if (!empty($result['errcode']) || empty($result['openid'])) {
            return ['code' => 201, 'msg' => '拉取用户信息失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 用户同意授权回调
     * @param array $config 微信公众号配置
     * @param string $code 第一步获取的code参数
     * @return array
     */
    public static function auth_call_back(array $config, string $code): array
    {
        // 获取 access token
        $access_token = WeChatOffi::auth_access_token($config, $code);
        if ($access_token['code'] != 200) {
            return ['code' => 201, 'msg' => $access_token['msg']];
        }
        // 获取用户信息
        $auth_user_info = WeChatOffi::auth_user_info($access_token['data']['access_token'], $access_token['data']['openid']);
        if ($auth_user_info['code'] != 200) {
            return ['code' => 201, 'msg' => $auth_user_info['msg']];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $auth_user_info['data']];
    }

    /**
     * 获取素材列表
     * 见 https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Get_materials_list.html
     * @param array $config 微信公众号配置
     * @param array $data 分页等参数
     * @return array
     */
    public static function batchget_material(array $config, array $data): array
    {
        // 获取 access token
        $access_token = WeChatOffi::access_token($config);
        if ($access_token['code'] != 200) {
            return ['code' => 201, 'msg' => $access_token['msg']];
        }
        // 获取素材列表
        $result = json_decode(Helper::post_curl(
            "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token={$access_token['data']['access_token']}",
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ), true);
        if (!empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '获取素材列表失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 模板消息【发送模板消息】
     * 见 https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html#发送模板消息
     * @param array $config 微信公众号配置
     * @param array $data 模板数据
     * @return array
     */
    public static function template_send(array $config, array $data): array
    {
        // 获取 access token
        $access_token = WeChatOffi::access_token($config);
        if ($access_token['code'] != 200) {
            return ['code' => 201, 'msg' => $access_token['msg']];
        }
        // 发送模板消息
        $result = json_decode(Helper::post_curl(
            "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token['data']['access_token']}",
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ), true);
        if (!empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '发送模板消息失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 创建菜单
     * 见 https://developers.weixin.qq.com/doc/offiaccount/Custom_Menus/Creating_Custom-Defined_Menu.html
     * @param array $config 微信公众号配置
     * @param array $menu_data 菜单按钮数据
     * @return array
     */
    public static function menu_create(array $config, array $menu_data): array
    {
        // 获取 access token
        $access_token = WeChatOffi::access_token($config);
        if ($access_token['code'] != 200) {
            return ['code' => 201, 'msg' => $access_token['msg']];
        }
        // 发布菜单
        $result = json_decode(Helper::post_curl(
            "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$access_token['data']['access_token']}",
            json_encode($menu_data, JSON_UNESCAPED_UNICODE)
        ), true);
        if (!empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '自定义菜单发布失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 微信token认证
     * @param array $data 数据
     * @return bool
     */
    public static function check_signature(array $data): bool
    {
        // 设置Token
        $token = 'token';
        // 1）将token、timestamp、nonce三个参数进行字典序排序
        $tmp_arr = [$data['nonce'], $data['timestamp'], $token];
        // 2）将三个参数字符串拼接成一个字符串进行sha1加密
        sort($tmp_arr, SORT_STRING);
        $str = implode($tmp_arr);
        $sign = sha1($str);
        // 3）开发者获得加密后的字符串可与signature对比，标识该请求来源于微信
        if ($sign == $data['signature']) {
            return true;
        }
        return false;
    }

    /**
     * 处理消息类型
     * @param object $object 回调数据
     * @return string
     */
    public static function response_msg(object $object): string
    {
        return WeChatOffi::text_xml_message($object, '您好，我是自动回复机器人，我还在学习中，暂时无法回复您的消息，敬请谅解！');
    }

    /**
     * 获取 access_token
     * 见 https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Get_access_token.html
     * @param array $config 微信公众号配置
     * @return array
     */
    protected static function access_token(array $config): array
    {
        // 获取本地token
        $access_token = Cache::get('offi_access_token');
        if (!empty($access_token)) {
            return ['code' => 200, 'msg' => 'success', 'data' => json_decode($access_token, true)];
        }
        // 获取微信token
        $result = json_decode(Helper::get_curl('https://api.weixin.qq.com/cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid' => $config['offi_app_id'],
            'secret' => $config['offi_app_secret'],
        ]), true);
        if (empty($result['access_token']) || !empty($result['errcode'])) {
            return ['code' => 201, 'msg' => '获取 Access Token 失败.'];
        }
        // 设置token，过期时间1.9小时
        $access_token = Cache::put('offi_access_token', json_encode($result, JSON_UNESCAPED_UNICODE), 114);
        if (empty($access_token)) {
            return ['code' => 201, 'msg' => 'Access Token 设置失败.'];
        }
        return ['code' => 200, 'msg' => 'success', 'data' => $result];
    }

    /**
     * 文本XML格式
     * @param object $object 回调数据
     * @param string $content 回复内容
     * @return string
     */
    protected static function text_xml_message(object $object, string $content): string
    {
        return '
<xml>
    <ToUserName><![CDATA[' . $object->FromUserName . ']]></ToUserName>
    <FromUserName><![CDATA[' . $object->ToUserName . ']]></FromUserName>
    <CreateTime>' . time() . '</CreateTime>
    <MsgType><![CDATA[text]]></MsgType>
    <Content><![CDATA[' . $content . ']]></Content>
</xml>';
    }
}
