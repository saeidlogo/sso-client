<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

## SSO Routing
Route::get('sso/profile', 'Moontius\SSOService\Http\SignOnController@profile')->name('sso.profile');
Route::get('sso/logout', 'Moontius\SSOService\Http\SignOnController@logout')->name('sso.logout');
Route::get('sso/signon', 'Moontius\SSOService\Http\SignOnController@signon')->name('sso.start');
Route::post('sso/email/signin', 'Moontius\SSOService\Http\SignOnController@email_signin')->name('sso.email.signin');
Route::post('sso/email/signup', 'Moontius\SSOService\Http\SignOnController@email_signup')->name('sso.email.signup');
Route::get('sso/email/verification', 'Moontius\SSOService\Http\SignOnController@email_verify')->name('sso.email.verify');
Route::get('sso/signon/{provider}', 'Moontius\SSOService\Http\SignOnController@social_signon')->name('sso.login');
Route::get('sso/signon/{provider}/callback', 'Moontius\SSOService\Http\SignOnController@social_callback')->name('sso.callback');

## mobile verification routing
Route::get('sso/signon/mobile', 'Moontius\SSOService\Http\SignOnController@signon_mobile')->name('sso.mobile.form');
Route::post('sso/signon/mobile', 'Moontius\SSOService\Http\SignOnController@signon_mobile')->name('sso.mobile.submit');
Route::get('sso/signon/mobile/verify', 'Moontius\SSOService\Http\SignOnController@signon_mobile_verify')->name('sso.verify.form');
Route::post('sso/signon/mobile/verify', 'Moontius\SSOService\Http\SignOnController@signon_mobile_verify')->name('sso.verify.submit');