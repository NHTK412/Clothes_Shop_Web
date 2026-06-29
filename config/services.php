<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ghn' => [
        'base_url' => env('GHN_BASE_URL', 'https://dev-online-gateway.ghn.vn'),
        'token' => env('GHN_TOKEN'),
        'webhook_token' => env('GHN_WEBHOOK_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'verify_ssl' => env('GHN_VERIFY_SSL', true),
        'default_service_type_id' => env('GHN_DEFAULT_SERVICE_TYPE_ID', 2),
        'default_weight' => env('GHN_DEFAULT_WEIGHT', 300),
        'default_length' => env('GHN_DEFAULT_LENGTH', 25),
        'default_width' => env('GHN_DEFAULT_WIDTH', 20),
        'default_height' => env('GHN_DEFAULT_HEIGHT', 3),
    ],

    'vnpay' => [
        'payment_url' => env('VNPAY_PAYMENT_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'return_url' => env('VNPAY_RETURN_URL'),
        'version' => env('VNPAY_VERSION', '2.1.0'),
        'command' => env('VNPAY_COMMAND', 'pay'),
        'currency' => env('VNPAY_CURRENCY', 'VND'),
        'locale' => env('VNPAY_LOCALE', 'vn'),
        'order_type' => env('VNPAY_ORDER_TYPE', 'other'),
        'expire_minutes' => env('VNPAY_EXPIRE_MINUTES', 15),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'secure' => env('CLOUDINARY_SECURE', true),
    ],

];
