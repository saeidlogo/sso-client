<?php

return [
    /*
      |--------------------------------------------------------------------------
      | Config Authentication
      |--------------------------------------------------------------------------
      |
      | You'll need to create a public and private key for your server.
      | These keys must be safely stored and should not change.
      |
     */

    'config' => [
        //Location where to redirect users once they authenticate with a provider
        'callback' => env('SSO_CALLBACK_URL', 'https://localhost/login/callback'),
        'ws_callback' => env('WS_SSO_CALLBACK_URL', 'https://localhost/login/callback'),
        'uid' => env('SSO_UID_OBJECT', 'SocialSignOn'),
        'steps' => ['SocialSignOn' => ['email', 'google', 'facebook',
            'view' => 'sso.start','handler'=>'\Moontius\SSOService\Http\WsSignOnController::social_redirect'],
            'VerifiedMobilePhone' => ['my', 'mobile', 'view' => 'sso.mobile.form'],
            'XeroOAuth' => ['view' => 'xero.oauth'],
            'XeroBankMapping' => ['last' => 'sso.profile', 'view' => 'xero.account.list'], 'last' => 'sso.profile'],
        'user_table_map' => [
            'uid' => 'email',
            'table' => 'app_users',
            'table_id' => 'id',
            'field_mapping' => [
                'email' => 'email',
                'username' => null,
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'password' => 'password',
                'phone' => 'phone']
        ],
        //Providers specifics
        'providers' => [
            'Google' => [
                'enabled' => true, //Optional: indicates whether to enable or disable adapter. Defaults to false
                'keys' => [
                    'key' => env('GOOGLE_CLIENT_ID', ''),
                    'secret' => env('GOOGLE_CLIENT_SECRET', '')
                ]
            ],
            'Facebook' => [
                'enabled' => true, //Optional: indicates whether to enable or disable adapter. Defaults to false
                'keys' => [
                    'key' => env('FACEBOOK_CLIENT_ID', ''),
                    'secret' => env('FACEBOOK_CLIENT_SECRET', '')
                ]
            ]
        ]
    ]
];
