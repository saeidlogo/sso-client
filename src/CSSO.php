<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

use Moontius\SSOService\Models\SsoUser;
use Illuminate\Support\Facades\DB;
use Moontius\SSOService\SSOException;

/**
 * Description of CSSO
 *
 * @author saeidlogo
 */
class CSSO implements ICSSO {

    public static $SSO_USERS_KEY = "sso_users";
    public static $SSO_XERO_KEY = "xero_oauth";
    public $next = null;
    public $config;
    public $params;
    public $stage;
    public $user;
    public $view;
    public $last;
    public $stepDone = false;
    public $uidObject = null;
    public $platform = 'laravel'; # 'wordpress', 'eloquent'

    function __construct($platform, $uidObject, $user = null) {
        $this->stage = __CLASS__;
        $this->stepDone = true;
        $this->platform = $platform;
        $this->uidObject = $uidObject;
    }

    public function getUidObject() {
        return $this->uidObject;
    }

    function mail_log($message) {
        //DO Mailing and log
    }

    function setUidObject($step) {
        $this->uidObject = $step;
    }

    function add_sso_user_by_phone($phone): SsoUser {
        $ssoUser = new SsoUser();
        $ssoUser->email = '';
        $ssoUser->phone = $phone;
        $ssoUser->save();
        return $ssoUser;
    }

    function update_sso_user_phone($ssoUser, $phone): SsoUser {
        $ssoUser->phone = $phone;
        $ssoUser->save();
        return $ssoUser;
    }

    function add_sso_user_by_email($email): SsoUser {
        $ssoUser = new SsoUser();
        $ssoUser->email = $email;
        $ssoUser->email_verified = 'TRUE';
        $ssoUser->uuid = md5($ssoUser->email . rand(1000, 9999));
        $ssoUser->save();
        return $ssoUser;
    }

    function add_sso_user($sso_social): SsoUser {
        $ssoUser = SsoUser::where(['email' => $sso_social->emailVerified])->first();
        if (!isset($ssoUser)) {
            $ssoUser = new SsoUser();
            $ssoUser->email = $sso_social->emailVerified;
            $ssoUser->email_verified = 'TRUE';
            $ssoUser->phone = $sso_social->phone;
            $ssoUser->first_name = $sso_social->firstName;
            $ssoUser->last_name = $sso_social->lastName;
            $ssoUser->uuid = md5($ssoUser->email . rand(1000, 9999));
            $ssoUser->save();
        }
        return $ssoUser;
    }

    function update_sso_user($ssoUser, $sso_social): SsoUser {
        $ssoUser->email = $sso_social->emailVerified;
        $ssoUser->email_verified = 'TRUE';
//        $ssoUser->phone = $sso_social->phone;
        $ssoUser->first_name = $sso_social->firstName;
        $ssoUser->last_name = $sso_social->lastName;
        $ssoUser->save();
        return $ssoUser;
    }

    function get_app_user() {
        $user_id = $this->sso_session_get('user_id');
        $mapping = config('sso.config.user_table_map');
        $user = DB::table($mapping['table'])->find($user_id);
        return $user;
    }

    function find_app_user_by_id($user_id) {
        $mapping = config('sso.config.user_table_map');
        $user = DB::table($mapping['table'])->find($user_id);
        return $user;
    }

    function change_user_password($password) {
        $mapping = config('sso.config.user_table_map');
        $field_mapping = $mapping['field_mapping'];
        $id = $this->sso_session_get('user_id');
        if ($id > 0) {
            $id = DB::table($mapping['table'])
                    ->where($mapping['table_id'], $id)
                    ->update(array(
                $field_mapping['password'] => md5($password)));
            if ($id > 0) {
                return true;
            }
            throw new SSOException('Unable to change user passoword', 501);
        }
        throw new SSOException('User not found', 501);
    }

    function add_app_user($sso_user, $password = null) {

        $mapping = config('sso.config.user_table_map');

        // SELECT email FROM `appUserTable` WHERE `email` = $email
        // Lookup user by UID to check if user was previously added by application 
        $uid_fieled = $mapping['uid'];
        $uid_value = $mapping['field_mapping'][$uid_fieled];
        $user_id_obj = DB::table($mapping['table'])->select($mapping['table_id'])->where([$uid_fieled => $sso_user->$uid_value])->first();


        $fields = array();
        $field_mapping = $mapping['field_mapping'];
        // Strip null values from field mapping to avoid SQL insert error.
        foreach ($field_mapping as $mapItem => $mapValue) {
            if (!is_null($mapValue)) {
                $field = $field_mapping[$mapItem];
                $fields[$mapValue] = $sso_user->$field;
//                array_push($fields, [$mapItem => $sso_user->$field]);
            }
        }

        if (!is_null($password)) {
            $fields['password'] = md5($password);
        }

        if (!isset($user_id_obj)) {
            // Username was not found in application; we will insert a new row to add new user 
            $id = DB::table($mapping['table'])->insertGetId($fields);
            //INSERT `sso_user_map`
            DB::table('sso_users_map')
                    ->insertGetId(array(
                        'sso_user_id' => $sso_user->id,
                        'app_user_id' => $id));
        } else {
            // Username has existing record, we will update null values
            // To-do : Add settings to "overwrite new values" or "update null values only" or "none"
            // by default we do "update null values only"
            $id = $user_id_obj->id;
            $femail = $field_mapping['email'];
            $email_obj = DB::table($mapping['table'])->select($femail)->where([$mapping['table_id'] => $id])->first();
            if ($email_obj == null || is_null($email_obj->$femail)) {
                DB::table($mapping['table'])
                        ->where($mapping['table_id'], $id)
                        ->update(array(
                            $femail => $sso_user->email));
            }

            $ffname = $field_mapping['firstName'];
            $first_name = DB::table($mapping['table'])->select($ffname)->where([$mapping['table_id'] => $id])->first();
            if ($first_name == null || is_null($first_name->$ffname)) {
                DB::table($mapping['table'])
                        ->where($mapping['table_id'], $id)
                        ->update(array(
                            $ffname => $sso_user->first_name));
            }

            $flname = $field_mapping['lastName'];
            $last_name = DB::table($mapping['table'])->select($flname)->where([$mapping['table_id'] => $id])->first();
            if ($last_name == null || is_null($last_name->$flname)) {
                DB::table($mapping['table'])
                        ->where($mapping['table_id'], $id)
                        ->update(array(
                            $flname => $sso_user->lats_name));
            }

            $fphone = $field_mapping['phone'];
            $phone = DB::table($mapping['table'])->select($fphone)->where([$mapping['table_id'] => $id])->first();
            if ($phone == null || is_null($phone->$fphone)) {
                DB::table($mapping['table'])
                        ->where($mapping['table_id'], $id)
                        ->update(array(
                            $fphone => $sso_user->phone));
            }
        }

        return $id;
    }

    function setSteps($steps = []) {
        $steps_objects = [];

        foreach ($steps as $step => $config) {
            switch ($step) {
                case $this->read_class_name(SocialSignOn::class):
                    $step = new SocialSignOn($config);
                    $this->setNext($step);
                    break;

                case $this->read_class_name(VerifiedMobilePhone::class):
                    $this->setNext(new VerifiedMobilePhone($config));
                    break;

                case $this->read_class_name(WebPushPermission::class):
                    $this->setNext(new WebPushPermission($config));
                    break;

                case $this->read_class_name(LocationPermission::class):
                    $this->setNext(new LocationPermission($config));
                    break;
                case $this->read_class_name(XeroOAuth::class):
                    $this->setNext(new XeroOAuth($config));
                    break;
                case $this->read_class_name(XeroBankMapping::class):
                    $this->setNext(new XeroBankMapping($config));
                    break;

                default:
            }
        }
    }

    # set next object

    function setNext($nextObject) {
        if (is_object($this->next)) {
            $this->next->setNext($nextObject);
        } else {
            $this->next = $nextObject;
        }
    }

    function getSteps(&$array = []) {
        $this->validate();
        array_push($array, $this->read_class_name(get_called_class()));
        if (is_object($this->next)) {
            return $this->next->getSteps($array);
        } else {
            array_shift($array);
            return $array;
        }
    }

    function getCurrentStep() {
        // if this step is done and there is a next step check with next step
        if (!$this->stepDone) {
            return $this->read_class_name(get_called_class());
        } elseif ($this->stepDone == true && is_object($this->next)) {
            return $this->next->getCurrentStep();
        } else {
            return true;
        }
    }

    #config view UI

    function getViewParams($step) {
        # 
        if ($step == $this->read_class_name(get_called_class())) {
            # return current setting to be displayed in User selection as sign-in or sign-up
            return $array;
        } else {
            return $this->next->getViewParams($step);
        }
    }

    function initForm() {
        return true;
    }

    public function validate(&$params) {
        if (is_object($this->next))
            return $this->next->validate($params);
        else
            return true;
    }

    function read_class_name($class) {
        $clazz = explode("\\", $class);
        return end($clazz);
    }

    public function getUser(&$params) {
        $this->uidObject = $params;
        return $this->next->getUser($this->uidObject);
    }

    public function sso_session_has($key) {
        return isset($_SESSION['SSO'][$key]);
    }

    public function sso_session_get($key) {
        if (isset($_SESSION['SSO'][$key])) {
            return $_SESSION['SSO'][$key];
        }
        return null;
    }

    public function sso_session_put($key, $value) {
        $_SESSION['SSO'][$key] = $value;
    }

    public function sso_session_destroy($key) {
        if (isset($_SESSION['SSO'][$key])) {
            unset($_SESSION['SSO'][$key]);
        }
    }

    public function logout(): bool {
        try {
            session_start();
            $provider = $this->sso_session_get('provider');
            $params['provider'] = $provider;
            $hybridauth = \Illuminate\Support\Facades\App::make('Hybridauth', $params);
            $hybridauth->disconnectAllAdapters();
            $this->sso_session_destroy('provider');
            $this->sso_session_destroy('sso_users');
            return true;
        } catch (\Exception $exc) {
            throw new SSOException($exc->getMessage(), 501);
        }
    }

}
