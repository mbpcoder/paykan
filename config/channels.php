<?php

/**
 * Iran Payment configuration.
 *
 * In Laravel this file is published to config/channels.php and read via
 * config('channels.ipg.*'). In Symfony / plain PHP the same array shape is
 * loaded into the package's configuration repository (see the README).
 *
 * Values default to environment variables so secrets stay out of source.
 */

if (!function_exists('env')) {
    /**
     * Lightweight env() fallback for non-Laravel environments.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}

return [
    'ipg' => [
        // Default gateway used when no name is passed to PaymentChannelService.
        'default' => env('IPG_DEFAULT', 'zarinpal'),

        // Default callback URL the gateway returns the user to.
        'callback_url' => env('IPG_CALLBACK_URL'),

        'provider' => [
            'zarinpal' => [
                'token' => env('ZARINPAL_TOKEN'),
                'send_url' => env('ZARINPAL_SEND_URL', 'https://api.zarinpal.com/pg/v4/payment/request.json'),
                'payment_url' => env('ZARINPAL_PAYMENT_URL', 'https://www.zarinpal.com/pg/StartPay/'),
                'verify_url' => env('ZARINPAL_VERIFY_URL', 'https://api.zarinpal.com/pg/v4/payment/verify.json'),
            ],

            'pay' => [
                'token' => env('PAY_TOKEN'),
                'send_url' => env('PAY_SEND_URL', 'https://pay.ir/pg/send'),
                'payment_url' => env('PAY_PAYMENT_URL', 'https://pay.ir/pg/'),
                'verify_url' => env('PAY_VERIFY_URL', 'https://pay.ir/pg/verify'),
                // Optional outbound network interface/IP to bind cURL to.
                'bind_interface' => env('PAY_BIND_INTERFACE'),
            ],

            'jibit' => [
                'api_key' => env('JIBIT_API_KEY'),
                'secret_key' => env('JIBIT_SECRET_KEY'),
                'base_url' => env('JIBIT_BASE_URL', 'https://napi.jibit.ir/ppg/v3'),
                'proxy' => env('JIBIT_PROXY'),
            ],

            'sep' => [
                'token' => env('SEP_TOKEN'),
                'send_url' => env('SEP_SEND_URL'),
                'payment_url' => env('SEP_PAYMENT_URL'),
                'verify_url' => env('SEP_VERIFY_URL'),
                'callback_url' => env('SEP_CALLBACK_URL'),
            ],

            'PayPing' => [
                'token' => env('PAYPING_TOKEN'),
                'send_url' => env('PAYPING_SEND_URL', 'https://api.payping.ir/v2/pay'),
                'verify_url' => env('PAYPING_VERIFY_URL', 'https://api.payping.ir/v2/pay/verify'),
            ],

            'bahamta' => [
                'token' => env('BAHAMTA_TOKEN'),
                'send_url' => env('BAHAMTA_SEND_URL'),
                'verify_url' => env('BAHAMTA_VERIFY_URL'),
            ],

            'pay_star' => [
                'token' => env('PAYSTAR_TOKEN'),
                'gateway_id' => env('PAYSTAR_GATEWAY_ID'),
                'base_url' => env('PAYSTAR_BASE_URL', 'https://api.paystar.ir/api/pardakht'),
            ],
        ],
    ],
];
