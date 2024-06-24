### 微信

注意：本仓库学习使用，逻辑未测试，使用需谨慎。

封装了微信公众号、企业微信、微信支付、微信小程序。
学习教程，请查看 [微信官网](https://weixin.qq.com/)

### 安装

```
使用composer安装
composer require envern/wechat:1.0.*

或者在你的composer.json里require部分添加
"envern/wechat": "1.0.*"

```

### 微信配置

```
'wechat' => [
    # 微信公众号配置
    'offi' => [
        'offi_app_id' => env('WECHAT_OFFI_APPID', ''),// 公众号的唯一标识
        'offi_app_secret' => env('WECHAT_OFFI_SECRET', ''),// 公众号的appsecret
    ],
     # 企业微信配置
    'work' => [
        'work_corp_id' => env('WECHAT_WORK_CORPID', ''),// 企业号的标识
        'work_agent_id' => env('WECHAT_WORK_AGENTID', ''),// 企业应用的id
        'work_corp_secret' => env('WECHAT_WORK_SECRET', ''),// 企业号的appsecret
    ],
    # 微信小程序配置
    'mini' => [
        'mini_app_id' => env('WECHAT_MINI_APPID', ''),// 小程序的唯一标识
        'mini_app_secret' => env('WECHAT_MINI_SECRET', ''),// 小程序的appsecret
    ],
    # 微信支付配置
    'pay' => [
        'wechat_pay_app_id' => env('WECHAT_PAY_APPID', ''),// 微信支付商户号
        'wechat_pay_mch_id' => env('WECHAT_PAY_MCHID', ''),// 微信支付商户号
        'wechat_pay_key' => env('WECHAT_PAY_KEY', ''),// 微信支付密钥
        'wechat_pay_notify_url' => env('WECHAT_PAY_NOTIFY_URL', ''),// 微信支付回调地址
        'wechat_pay_cert_key' => env('WECHAT_PAY_CERT_KEY_PATH', ''),// 微信支付证书密钥
        'wechat_pay_cert_serial_number' => env('WECHAT_PAY_CERT_SERIAL_NUMBER', ''),// 微信支付证书序列号
    ]
]     
```

### 代码结构

``` 
wechat
 ├── src                    
 │   ├── Helper.php                             -- 辅助函数库
 │   ├── WeChatOffi.php                         -- 微信公众号
 │   ├── WeChatPay.php                          -- 微信支付
 │   ├── WeChatWork.php                         -- 企业微信
 │   └── WeChatMini.php                         -- 微信小程序
 ├── vendor                
 │   ├── composer
 │   │   ├── autoload_classmap.php
 │   │   ├── autoload_namespaces.php
 │   │   ├── autoload_psr4.php
 │   │   ├── autoload_real.php
 │   │   ├── autoload_static.php
 │   │   └── ClassLoader.php
 │   └── autoload.php
 ├── .gitignore                                 -- 过滤文件
 ├── composer.json                              -- composer 配置文件
 └── README.md                                  -- 自述文件
```
