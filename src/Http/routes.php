<?php

use Illuminate\Support\Facades\Route;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

## this routes should been put in web middleware to be handled session by laravel
Route::group(['middleware' => ['web']], function () {
## WS Sso Routing    
    Route::get('ws/sso/logout', '\Moontius\SSOService\Http\WsSignOnController@logout')->name('ws.sso.logout');
    Route::get('ws/sso/signon', '\Moontius\SSOService\Http\WsSignOnController@signon')->name('ws.sso.start');

    Route::get('ws/sso/social/{provider}/callback', '\App\Http\Socket\WsSignOnController@social_callback')->name('ws.sso.callback');

    Route::get('ws/sso/social/{provider}', '\Moontius\SSOService\Http\WsSignOnController@social_signon')->name('ws.sso.login');
    Route::get('ws/sso/social/{provider}/callback', '\Moontius\SSOService\Http\WsSignOnController@social_callback')->name('ws.sso.callback');

    Route::get('ws/sso/mobile', '\Moontius\SSOService\Http\WsSignOnController@signon_mobile_form')->name('ws.sso.mobile.form');
    Route::post('ws/sso/mobile/submit', '\Moontius\SSOService\Http\WsSignOnController@signon_mobile_form_post')->name('ws.sso.mobile.submit');

    Route::get('ws/sso/mobile/verify', '\Moontius\SSOService\Http\WsSignOnController@signon_verify_form')->name('ws.sso.verify.form');
    Route::post('ws/sso/mobile/verify/submit', '\Moontius\SSOService\Http\WsSignOnController@signon_verify_submit')->name('ws.sso.verify.submit');

    Route::get('ws/xero/oauth', '\Moontius\SSOService\Http\WsSignOnController@xero_oauth')->name('ws.xero.oauth');
    Route::get('ws/xero/oauth/authenticate', '\Moontius\SSOService\Http\WsSignOnController@xero_oauth_authenticate')->name('ws.xero.authenticate');
    Route::get('ws/xero/oauth/logout', '\Moontius\SSOService\Http\WsSignOnController@xero_oauth_logout')->name('ws.xero.logout');
    Route::get('ws/xero/oauth/callback', '\Moontius\SSOService\Http\WsSignOnController@xero_oauth_callback')->name('ws.xero.callback');
    Route::get('ws/xero/accounts/list', '\Moontius\SSOService\Http\WsSignOnController@xero_bank_accounts_selection')->name('ws.xero.account.list');
    Route::post('ws/xero/accounts/submit', '\Moontius\SSOService\Http\WsSignOnController@xero_bank_accounts_submit')->name('ws.xero.account.submit');

## SSO Routing
    Route::get('sso/logout', '\Moontius\SSOService\Http\SignOnController@logout')->name('sso.logout');
    Route::get('sso/signon', '\Moontius\SSOService\Http\SignOnController@signon')->name('sso.start');

    Route::get('sso/social/{provider}/callback', '\App\Http\Socket\SocketService@social_callback')->name('sso.callback');

    ## profile
    Route::get('sso/profile', '\Moontius\SSOService\Http\SignOnController@profile')->name('sso.profile');
    Route::get('user/password', '\Moontius\SSOService\Http\UserProfileController@change_password')->name('user.change.password');
    Route::post('user/password/submit', '\Moontius\SSOService\Http\UserProfileController@change_password_submit')->name('user.change.password.submit');

    Route::post('sso/email/signin', '\Moontius\SSOService\Http\SignOnController@email_signin')->name('sso.email.signin');
    Route::post('sso/email/signup', '\Moontius\SSOService\Http\SignOnController@email_signup')->name('sso.email.signup');
    Route::get('sso/email/verification', '\Moontius\SSOService\Http\SignOnController@email_verify')->name('sso.email.verify');

    Route::get('sso/social/{provider}', '\Moontius\SSOService\Http\SignOnController@social_signon')->name('sso.login');
    Route::get('sso/social/{provider}/callback', '\Moontius\SSOService\Http\SignOnController@social_callback')->name('sso.callback');
//
//## mobile verification routing
    Route::get('sso/mobile', '\Moontius\SSOService\Http\SignOnController@signon_mobile_form')->name('sso.mobile.form');
    Route::post('sso/mobile/submit', '\Moontius\SSOService\Http\SignOnController@signon_mobile_form_post')->name('sso.mobile.submit');

    Route::get('sso/mobile/verify', '\Moontius\SSOService\Http\SignOnController@signon_verify_form')->name('sso.verify.form');
    Route::post('sso/mobile/verify/submit', '\Moontius\SSOService\Http\SignOnController@signon_verify_submit')->name('sso.verify.submit');

    ## Xero account routing
    Route::get('xero/oauth', '\Moontius\SSOService\Http\SignOnController@xero_oauth')->name('xero.oauth');
    Route::get('xero/oauth/authenticate', '\Moontius\SSOService\Http\SignOnController@xero_oauth_authenticate')->name('xero.authenticate');
    Route::get('xero/oauth/logout', '\Moontius\SSOService\Http\SignOnController@xero_oauth_logout')->name('xero.logout');
    Route::get('xero/oauth/callback', '\Moontius\SSOService\Http\SignOnController@xero_oauth_callback')->name('xero.callback');
    Route::get('xero/accounts/list', '\Moontius\SSOService\Http\SignOnController@xero_bank_accounts_selection')->name('xero.account.list');
    Route::post('xero/accounts/submit', '\Moontius\SSOService\Http\SignOnController@xero_bank_accounts_submit')->name('xero.account.submit');
});
