<?php
declare(strict_types=1);

namespace Envern\WeChat;

/**
 * 辅助函数库
 * @package Envern\WeChat
 * @version V1.0
 */
class Helper
{
    /**
     * curl的POST请求
     * @param string $url 请求地址
     * @param array|string $params 请求参数
     * @param array $header 请求头
     * @return bool|string
     */
    public static function post_curl(string $url, array|string $params, array $header = []): bool|string
    {
        $ch = curl_init();//初始化
        curl_setopt($ch, CURLOPT_URL, $url);//请求地址
        curl_setopt($ch, CURLOPT_HEADER, false);//设定是否输出页面内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//设定是否显示头信息
        if (!empty($header)) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);//设定header参数
        //设定SSL证书安全校验
        $ssl = str_starts_with($url, 'https://');
        if ($ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//检查服务器SSL证书中是否存在一个公用名
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//设置为FALSE 禁止 cURL 验证对等证书
        }
        curl_setopt($ch, CURLOPT_POST, true);//设定请求方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);//post方式的时候添加数据
        $r = curl_exec($ch);//执行
        curl_close($ch);//关闭
        return $r;
    }

    /**
     * curl的GET请求
     * @param string $url 请求地址
     * @param array $params 请求参数
     * @param array $header 请求头
     * @return bool|string
     */
    public static function get_curl(string $url, array $params, array $header = []): bool|string
    {
        // 组装参数
        if (!empty($params)) $url .= (!str_contains($url, '?') ? '?' : '&') . http_build_query($params);
        $ch = curl_init();//初始化
        curl_setopt($ch, CURLOPT_HEADER, false);//设定是否输出页面内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//设定是否显示头信息
        if (!empty($header)) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);//设定header参数
        //设定SSL证书安全校验
        $ssl = str_starts_with($url, 'https://');
        if ($ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//是否禁止cURL验证对等证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//检查服务器SSL证书存在是否公用名
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);//设置curl允许执行的最长秒数
        curl_setopt($ch, CURLOPT_URL, $url);//请求地址
        $r = curl_exec($ch);//执行
        curl_close($ch);//关闭
        return $r;
    }
}
