<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Moontius\SSOService\Models\SsoClientsPhoneOtp;
use Moontius\SSOService\Models\SsoClientsPhone;
use Illuminate\Support\Facades\DB;
use Moontius\LaravelSMS\Exceptions\SMSException;
/**
 * Description of VerifiedMobilePhone
 *
 * @author saeidlogo
 */
class VerifiedMobilePhone extends CSSO {

    function __construct($configs = null) {
        foreach ($configs as $config => $value) {
            if ($config == 'view') {
                $this->view = $value;
            }
            if ($config == 'last') {
                $this->last = $value;
            }
        }
    }

    function process_mobile_form(&$params) {
        $countries = DB::table('sso_country_list')->get();

        //find user country code from nas table setting, only private IPs are accepted
        $user_country = $this->find_user_country(Request::ip());

        $params['countries'] = $countries;
        $params['user_country'] = $user_country;
        $params['show_mobile'] = true;
        if (isset($params['redirect'])) {
            $params['route'] = $this->view;
        }
    }

    function process_mobile_submit(&$params) {
        if (!isset($params['mobile'])) {
            throw new SSOException('Mobile number is required', 101);
        }

        $mobile = $params['mobile'];

        //validate mobile
        if (preg_match('/^\+[0-9][0-9]{1,}/s', $mobile) == false) {
            $error_msg = "You must provide a valid mobile number to receive SMS verification code";
            throw new SSOException($error_msg, 103);
        }

        $mobile = str_replace('+', '', $mobile);
        //check if mobile already exists in db
        $opt_verified = SsoClientsPhone::where('phone', $mobile)->exists();
        if ($opt_verified) {
            $error_msg = "This mobile is already registered, Please call 03-27306464";
            throw new SSOException($error_msg, 104);
        }

        //check if user does not exist in session
        $sso_users = $this->sso_session_get('sso_users');
        if (is_null($sso_users)) {
            $sso_users = $this->addUserSsoByPhone($mobile);
        }

        //Generate Verification code
        $v_code = (rand(1000, 9999));
        $country = collect(DB::select(DB::raw("SELECT get_country_code_iso2('$mobile') AS `country_code_iso2` ")))->first();
        $country_code_iso2 = $country->country_code_iso2;
        switch ($country_code_iso2) {
            case 'IR':
                $prefered = 'kavehnegar';
                break;
            case 'MY':
                $prefered = 'kannel';
                break;
            default:
                $prefered = 'kavehnegar';
                break;
        }


        //check if user requested OTP in the last 15 minutes 
        $exist = SsoClientsPhoneOtp::where('phone', $mobile)->whereRaw('datetime >= NOW() - INTERVAL ? MINUTE', env('USER_SMS_RETRY_LIMIT_MINUTES', 15))->exists();
        if ($exist) {
            throw new SSOException("You have already requested for verification message, please wait a while and try again.");
        }

        try {
            $msg = [
                'to' => $mobile,
                'text' => $v_code,
                'template' => 'verify'
            ];

            $sms = App::make('sms', ['number' => $mobile, 'prefered' => $prefered, 'type' => 'template']);
            $smsObject = $sms->send($msg);

            $clientPhoneOtp = $this->create_otp($mobile, $v_code, $smsObject);
            $clientPhoneOtp->save();
            $params['show_verify'] = false;
            $this->sso_session_put('mobile', $mobile);
            return true;
        } catch (SMSException $exc) {
            report($exc);
            throw new SSOException('Unable to send sms', SSOException::$ERROR_SMS_SEND_EXCEPTION);
        }
    }

    function process_verify_form(&$params) {
        
    }

    function process_verify_submit(&$params) {
        $cmd = isset($params['cmd']) ? $params['cmd'] : '';
        if ($cmd == 'back') {
            $this->sso_session_destroy('mobile');
            return true;
        }

        $sso_users = $this->sso_session_get('sso_users');

        $user_mobile = $this->sso_session_get('mobile');
        if (!isset($user_mobile)) {
            throw new Exception('Mobile number is required', 101);
        }
        if (!isset($params['v_code'])) {
            throw new SSOException('Verification Code is required', 101);
        }
        $code = $params['v_code'];

        //check opt expiration 
        $ssoClientOtp = SsoClientsPhoneOtp::where('phone', $user_mobile)->whereRaw('datetime >= NOW() - INTERVAL ? MINUTE', 15)->orderBy('datetime', 'DESC')->first();
        if (is_null($ssoClientOtp)) {
            throw new SSOException('expired token', 101);
        }

        //check user verification OTP try
        $verify_limit = env('USER_SMS_VERIFY_FAILED_LIMIT', 3);
        if ($ssoClientOtp->failed_attempt > $verify_limit) {
            throw new SSOException('expired token', SSOException::$ERROR_INVALID_TOKEN);
        }

        $expected_code = $ssoClientOtp->otp;
        $mobile = $ssoClientOtp->phone;


        //validate verification code and compare it with recorded OTP that should be equal
        if (!preg_match('/^\d{4}$/s', $code) || $expected_code !== (int) $code) {
            $ssoClientOtp->failed_attempt++;
            $ssoClientOtp->save();
            $error_msg = 'Wrong verification code, Please call +6-03-27306464 to get free access';
            throw new SSOException($error_msg, 101);
        }

        DB::beginTransaction();
        try {
            //create client phone record to track user phone verification history
            $ssoClientPhone = new SsoClientsPhone();
            $ssoClientPhone->clientid = $sso_users->id;
            $ssoClientPhone->phone = $mobile;
            $user_ip = Request::ip();
            $ssoClientPhone->user_ip_address = ip2long($user_ip);
            $ssoClientPhone->save();

            #check if this is primary registration object
            $uid = config('sso.config.uid');
            //if uid is equals to current class name then should we do insert new sso user record
            if ($uid == $this->read_class_name(get_called_class())) {
                $sso_users = $this->add_sso_user_by_phone($mobile);
            } else {
                $sso_users = $this->update_sso_user_phone($sso_users, $mobile);
            }

            $app_user_id = $this->add_app_user($sso_users);

            //remove mobile key from session
            $this->sso_session_destroy('mobile');
            $this->user = $sso_users;
            $this->sso_session_put(CSSO::$SSO_USERS_KEY, $sso_users);
            $this->sso_session_put('user_id', $app_user_id);
            $this->stepDone = true;
            DB::commit();
            $params['redirect'] = true;
        } catch (Exception $exc) {
            DB::rollBack();
            throw new SSOException($exc->getMessage(), 501);
        }
    }

    function validate(&$params) {
        $sso_users = $this->sso_session_get(CSSO::$SSO_USERS_KEY);
        //check if sso user has a phone then validate current step
        if (!is_null($sso_users)) {
            if (isset($sso_users->phone) && !empty($sso_users->phone)) {
                $this->stepDone = true;
                if ($this->last) {
                }
            }
        }



        if (!$this->stepDone) {
            if (isset($params['verify_state'])) {
                $state = $params['verify_state'];
            } else {
                $is_mobile = $this->sso_session_get('mobile');
                if (isset($is_mobile)) {
                    $method = isset($params['method']) ? $params['method'] : 'GET';
                    if ($method == 'GET') {
                        $state = 'verify_form';
                    } else {
                        $state = 'verify_submit';
                    }
                } else {
                    $state = isset($params['verify_state']) ? $params['verify_state'] : 'mobile_form';
                }
            }

            switch ($state) {
                case 'mobile_form':
                    $this->process_mobile_form($params);
                    break;
                case 'mobile_submit':
                    $this->process_mobile_submit($params);
                    break;
                case 'verify_form':
                    $this->process_verify_form($params);
                    break;
                case 'verify_submit':
                    $this->process_verify_submit($params);
                    break;
                default;
                    break;
            }
        }

        if ($this->stepDone) {
            if (is_object($this->next))
                return $this->next->validate($params);
        } else
            return $this->stepDone;
    }

    public function getViewParams($step) {
        if ($step[0] == $this->read_class_name(get_called_class())) {
            # return current setting to be displayed in User selection as sign-in or sign-up
            return array();
        } elseif (is_object($this->next)) {
            return $this->next->getViewParams($step);
        } else {
            return array();
        }
    }

    function getCurrentStep() {
        $ssoUser = $this->sso_session_get(CSSO::$SSO_USERS_KEY);
        if (!is_null($ssoUser)) {
            if (isset($ssoUser->phone) && !empty($ssoUser->phone)) {
                $this->stepDone = true;
            }
        }
        if (!$this->stepDone) {
            return $this->read_class_name(get_called_class());
        } elseif ($this->stepDone == true && is_object($this->next)) {
            return $this->next->getCurrentStep();
        } else {
            if (isset($this->last)) {
                return $this->last;
            }
            return $this->view;
        }
    }

    function initForm() {
        return '';
    }

    function find_user_country($reqIp) {
        //10.0.0.0-10.255.255.255 or 172.16.0.0 to 172.16.254.254 or 192.168.0.0 to 192.168.254.254
        $userIp = ip2long($reqIp);
        $pip_class_a_start = ip2long('10.0.0.0');
        $pip_class_a_end = ip2long('10.255.255.255');

        $pip_class_b_start = ip2long('172.16.0.0');
        $pip_class_b_end = ip2long('172.16.254.254');

        $pip_class_c_start = ip2long('192.168.0.0');
        $pip_class_c_end = ip2long('192.168.254.254');
        $user_country = 'IR';
        if (($userIp > $pip_class_a_start && $userIp <= $pip_class_a_end) || ($userIp > $pip_class_b_start && $userIp <= $pip_class_b_end) || ($userIp > $pip_class_c_start && $userIp <= $pip_class_c_end)) {
            $nasSettrings = NasHotspotSettings::select('country_code_iso2 as code')
                            ->where('end_ip', '>=', $userIp)->where('start_ip', '<=', $userIp)->first();
            if (!is_null($nasSettrings)) {
                $user_country = $nasSettrings->code;
            }
        }
        return $user_country;
    }

    public function getUser(&$uidObject) {
        # check if the current class is elected as user identifier object and return the user_id set by this class
        if ($this->read_class_name(get_called_class()) == $uidObject) {
            $this->user = $this->sso_session_get(CSSO::$SSO_USERS_KEY);
            return $this->user;
        } elseif (is_object($this->next)) {
            return $this->next->getUser($uidObject);
        } else {
            if ($this->last) {
                $this->user = $this->sso_session_get(CSSO::$SSO_USERS_KEY);
                return $this->user;
            }
            return $this->user;
        }
    }

    function create_otp($mobile, $token, $obj): SsoClientsPhoneOtp {
        $country = DB::raw('get_country_code_iso2(\'' . $mobile . '\')');
        $otp = new SsoClientsPhoneOtp();
        $ip = Request::ip();
//        $otp->clientid = isset($user->id) ? $user->id : -1;
        $otp->phone = $mobile;
        $otp->otp = $token;
        $otp->country = $country;
        $otp->user_ip_address = ip2long($ip);
        $otp->failed_attempt = 0;
        $otp->messageid = $obj->messageid;
        $otp->network = $obj->network;
        $otp->cost = $obj->cost;
        return $otp;
    }

}
