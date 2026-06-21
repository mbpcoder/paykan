<?php

/**
 * Payment configuration.
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
                'terminal_id' => env('SEP_TERMINAL_ID'),
                'base_url' => env('SEP_BASE_URL'),
                'pay_base_url' => env('SEP_PAY_BASE_URL'),
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

            // ---- Gateways ported from farayaz/larapay ----

            'vandar' => [
                'api_key' => env('VANDAR_API_KEY'),
            ],

            'zibal' => [
                'merchant' => env('ZIBAL_MERCHANT'),
            ],

            'beh_pardakht' => [
                'terminal_id' => env('BEHPARDAKHT_TERMINAL_ID'),
                'username' => env('BEHPARDAKHT_USERNAME'),
                'password' => env('BEHPARDAKHT_PASSWORD'),
                'is_credit' => env('BEHPARDAKHT_IS_CREDIT', false),
                'base_url' => env('BEHPARDAKHT_BASE_URL'),
            ],

            'asan_pardakht' => [
                'username' => env('ASANPARDAKHT_USERNAME'),
                'password' => env('ASANPARDAKHT_PASSWORD'),
                'merchant_configuration_id' => env('ASANPARDAKHT_MERCHANT_CONFIGURATION_ID'),
            ],

            'azkivam' => [
                'merchant_id' => env('AZKIVAM_MERCHANT_ID'),
                'api_key' => env('AZKIVAM_API_KEY'),
            ],

            'bitpay' => [
                'api' => env('BITPAY_API'),
                'sandbox' => env('BITPAY_SANDBOX', false),
            ],

            'digipay' => [
                'username' => env('DIGIPAY_USERNAME'),
                'password' => env('DIGIPAY_PASSWORD'),
                'client_id' => env('DIGIPAY_CLIENT_ID'),
                'client_secret' => env('DIGIPAY_CLIENT_SECRET'),
            ],

            'ecd' => [
                'terminal_number' => env('ECD_TERMINAL_NUMBER'),
                'hash_key' => env('ECD_HASH_KEY'),
            ],

            'fanava_card' => [
                'user_id' => env('FANAVACARD_USER_ID'),
                'password' => env('FANAVACARD_PASSWORD'),
            ],

            'iran_dargah' => [
                'merchant_id' => env('IRANDARGAH_MERCHANT_ID'),
                'sandbox' => env('IRANDARGAH_SANDBOX', false),
            ],

            'iran_kish' => [
                'terminalId' => env('IRANKISH_TERMINAL_ID'),
                'password' => env('IRANKISH_PASSWORD'),
                'acceptorId' => env('IRANKISH_ACCEPTOR_ID'),
                'pubKey' => env('IRANKISH_PUB_KEY'),
            ],

            'isipayment_samin' => [
                'merchant_code' => env('ISIPAYMENT_MERCHANT_CODE'),
                'merchant_password' => env('ISIPAYMENT_MERCHANT_PASSWORD'),
                'terminal_code' => env('ISIPAYMENT_TERMINAL_CODE'),
                'type' => env('ISIPAYMENT_TYPE'),
                'number_of_installment' => env('ISIPAYMENT_NUMBER_OF_INSTALLMENT'),
            ],

            'keepa' => [
                'token' => env('KEEPA_TOKEN'),
            ],

            'mehr_iran' => [
                'terminal_id' => env('MEHRIRAN_TERMINAL_ID'),
                'merchant_nid' => env('MEHRIRAN_MERCHANT_NID'),
                'encrypt_key' => env('MEHRIRAN_ENCRYPT_KEY'),
            ],

            'next_pay' => [
                'api_key' => env('NEXTPAY_API_KEY'),
            ],

            'omidpay' => [
                'user_id' => env('OMIDPAY_USER_ID'),
                'password' => env('OMIDPAY_PASSWORD'),
            ],

            'pec' => [
                'login_account' => env('PEC_LOGIN_ACCOUNT'),
            ],

            'pep' => [
                'username' => env('PEP_USERNAME'),
                'password' => env('PEP_PASSWORD'),
                'terminal_number' => env('PEP_TERMINAL_NUMBER'),
            ],

            'pardakht_novin' => [
                'userId' => env('PNA_USER_ID'),
                'password' => env('PNA_PASSWORD'),
                'terminalId' => env('PNA_TERMINAL_ID'),
            ],

            'polam' => [
                'api_key' => env('POLAM_API_KEY'),
            ],

            'refah_beta' => [
                'client_id' => env('REFAHBETA_CLIENT_ID'),
                'client_secret' => env('REFAHBETA_CLIENT_SECRET'),
                'api_key' => env('REFAHBETA_API_KEY'),
                'number_of_installments' => env('REFAHBETA_NUMBER_OF_INSTALLMENTS'),
            ],

            'sadad' => [
                'terminal_id' => env('SADAD_TERMINAL_ID'),
                'merchant_id' => env('SADAD_MERCHANT_ID'),
                'key' => env('SADAD_KEY'),
                'base_url' => env('SADAD_BASE_URL'),
                'pay_base_url' => env('SADAD_PAY_BASE_URL'),
            ],

            'sadad_bnpl' => [
                'terminal_id' => env('SADADBNPL_TERMINAL_ID'),
                'merchant_id' => env('SADADBNPL_MERCHANT_ID'),
                'key' => env('SADADBNPL_KEY'),
            ],

            'sepal' => [
                'api_key' => env('SEPAL_API_KEY'),
            ],

            'sepehrpay' => [
                'terminalId' => env('SEPEHRPAY_TERMINAL_ID'),
                'base_url' => env('SEPEHRPAY_BASE_URL'),
                'pay_base_url' => env('SEPEHRPAY_PAY_BASE_URL'),
            ],

            'shepa' => [
                'api' => env('SHEPA_API'),
            ],

            'snapp_pay' => [
                'username' => env('SNAPPPAY_USERNAME'),
                'password' => env('SNAPPPAY_PASSWORD'),
                'client_id' => env('SNAPPPAY_CLIENT_ID'),
                'client_secret' => env('SNAPPPAY_CLIENT_SECRET'),
            ],

            'taba_pay' => [
                'token' => env('TABAPAY_TOKEN'),
            ],

            'tejarat_bajet' => [
                'client_id' => env('TEJARATBAJET_CLIENT_ID'),
                'client_secret' => env('TEJARATBAJET_CLIENT_SECRET'),
                'username' => env('TEJARATBAJET_USERNAME'),
                'password' => env('TEJARATBAJET_PASSWORD'),
                'sandbox' => env('TEJARATBAJET_SANDBOX', false),
            ],
        ],
    ],
];
