<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

use Moontius\SSOService\Models\SsoSocial;
use Moontius\SSOService\SSOException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Moontius\SSOService\Models\SsoUser;
use Illuminate\Support\Facades\Mail;
use Moontius\SSOService\Models\SsoEmailToken;
use Moontius\SSOService\Mail\VerificationMail;
use Hybridauth\HttpClient;

/**
 * Description of SocialSignOn
 *
 * @author saeidlogo
 */
class SocialSignOn extends CSSO {

    public $email = false;
    public $user = null;
    public $stepDone = false;
    public $handler;

    /**
     * Mailer instance.
     *
     * @var \Illuminate\Contracts\Mail\Mailer
     */
    protected $mailer;

    function __construct($configs = null) {
        foreach ($configs as $config => $value) {
            if ($config == 'email') {
                $this->email = $value;
            }
            if ($config == 'view') {
                $this->view = $value;
            }
            if ($config == 'last') {
                $this->last = $value;
            }
            if ($config == 'handler') {
                $this->handler = $value;
            }
        }
    }

    public function getViewParams($step) {
        if ($step[0] == $this->read_class_name(get_called_class())) {
            # return current setting to be displayed in User selection as sign-in or sign-up
            return array();
        } elseif (is_object($this->next)) {
            return $this->next->getViewParams($step);
        } else {
            return $this->view;
        }
    }

    public function getUser(&$uidObject) {
        # check if the current class is elected as user identifier object and return the user_id set by this class
        if ($this->read_class_name(get_called_class()) == $uidObject) {
            $this->user = $this->sso_session_get(CSSO::$SSO_USERS_KEY);
            return $this->user;
        } else {
            return $this->next->getUser($uidObject);
        }
    }

    public function do_sso(&$params): bool {
        $provider = $params['provider'];
        $hybridauth = App::make('Hybridauth', $params);

        $array = $hybridauth->getConnectedProviders();
        //if user already sign on with sso then return true and exist proceding validation chains
        if (!empty($array)) {
            $this->sso_session_put('provider', $provider);
            return true;
        }
        //authenticate user for current provider
        if (isset($this->handler)) {
            HttpClient\Util::setRedirectHandler($this->handler);
            $adapter = $hybridauth->authenticate($provider);
            return false;
        }

        return $adapter->isConnected();
    }

    public function do_sso_callback(&$params) {
        if (!$this->stepDone) {
            $provider = $params['provider'];
            $hybridauth = App::make('Hybridauth', $params);
            $adapter = $hybridauth->authenticate(ucfirst($provider));
            $isConnected = $hybridauth->isConnectedWith($provider);
            if ($isConnected) {
                $userProfile = $adapter->getUserProfile();
                //check if social_id already exist in db
//                $result = DB::transaction(function () use($session, $userProfile, $provider) {
                DB::beginTransaction();
                try {
                    $sso_users = new SsoUser();
                    $sso_social = SsoSocial::where('socialid', $userProfile->identifier)->first();
                    //if sso social record not exist
                    if (is_null($sso_social)) {
                        //create new sso_socials
                        $sso_social = $this->create_sso_social($provider, $userProfile);
                        $sso_social->save();
                        #check if this is primary registration object
                        $uid = config('sso.config.uid');
                        //if uid is equals to current class name then should we do insert new sso user record
                        if ($uid == $this->read_class_name(get_called_class())) {
                            $sso_users = $this->add_sso_user($sso_social);
                        } else {
                            $sso_users = $this->update_sso_user($sso_users, $sso_social);
                        }

                        //update sso social table with sso user id
                        $sso_social->sso_user_id = $sso_users->id;
                        $sso_social->save();
                    } else {
                        $sso_users = SsoUser::where('email', $sso_social->emailVerified)->first();
                    }

                    $app_user_id = $this->add_app_user($sso_users, null);
                    $this->user = $sso_users;
                    $this->sso_session_put(CSSO::$SSO_USERS_KEY, $sso_users);
                    $this->sso_session_put('provider', $provider);
                    $this->sso_session_put('user_id', $app_user_id);
                    $this->stepDone = true;
                    DB::commit();
                    $params['redirect'] = true;
                } catch (\Exception $exc) {
                    $hybridauth->disconnectAllAdapters();
                    DB::rollBack();
                    throw new SSOException($exc->getMessage(), 501);
                }
//                        });
            }
        }

        return true;
    }

    public function create_sso_social($provider, $userProfile): SsoSocial {
        $sso_social = new SsoSocial();
        $sso_social->socialid = $userProfile->identifier;
        $sso_social->provider = $provider;
        $sso_social->firstName = $userProfile->firstName;
        $sso_social->lastName = $userProfile->lastName;

        $sso_social->profileURL = $userProfile->profileURL;
        $sso_social->photoURL = $userProfile->photoURL;
        $sso_social->emailVerified = $userProfile->emailVerified;
        return $sso_social;
    }

    public function do_logout(&$params): bool {
        $provider = $this->sso_session_get('provider');
        if (!is_null($provider)) {
            $this->sso_session_destroy('provider');
            $params['provider'] = $provider;
            $hybridauth = App::make('Hybridauth', $params);
            $hybridauth->disconnectAllAdapters();
        }
    }

    public function do_username_auth(&$params): bool {
        // lookup email or username from config
        $mapping = config('sso.config.user_table_map');
        if ($this->platform == 'laravel') {
            $username = $params['username'];
            $password = $params['password'];
            $app_user = DB::table($mapping['table'])->where([$mapping['uid'] => $username, 'password' => md5($password)])->first();
            if (isset($app_user)) {
                $id = $mapping['table_id'];
                $sso_user_id = DB::table('sso_users_map')->select('sso_user_id')->where(['app_user_id' => $app_user->$id])->first();
                $sso_user = SsoUser::find($sso_user_id->sso_user_id);
                if (isset($sso_user)) {
                    $this->sso_session_put(CSSO::$SSO_USERS_KEY, $sso_user);
                    $this->sso_session_put('provider', 'email');
                    $params['redirect'] = true;
                    return true;
                }
                throw new SSOException('unable to find sso_user related to current user id', 102);
            }
            throw new SSOException('Invalid username or password', 101);
        } else {
            $db_query = "SELECT * FROM `sso_users` WHERE `email`='$email' AND `password` = '" . md5($password) . "'";
            return (count($db_query) > 0);
        }

        if ($email->verified) {
            $this->user = $email->uid;
            return true;
        }
        throw new SSOException("Email is not verified yet", 502);
    }

    function create_sso_email_token($params): SsoEmailToken {
        $email_token = new SsoEmailToken();
        $email_token->token = $this->generateToken();
        $email_token->email = $params['email'];
        $email_token->password = $params['password'];
        $email_token->save();
        return $email_token;
    }

    public function do_email_create(&$params): bool {
        $email = $params['email'];

        //if token already exists for current email address throw ssoexception
        $token_exists = SsoEmailToken::where(['email' => $email])->whereRaw('created_at >= NOW() - INTERVAL ? MINUTE', 15)->orderBy('created_at', 'DESC')->exists();
        if ($token_exists) {
            throw new SSOException("token already sent to your email account please verify your email to continue registration process, or try again 15 minutes later", 501);
        }

        //if emila address already exists  address throw ssoexception
        $user_exist = SsoUser::where(['email' => $email])->exists();
        if ($user_exist) {
            throw new SSOException("Email already exists", 501);
        }


        $email_token = $this->create_sso_email_token($params);
        $this->send_verification_email($email_token);
        $params['view'] = 'sso.verify';
        $params['message'] = 'A verification email has been sent to your account please verify your email to continue registration process';
        return false;
    }

    public function do_email_verify(&$params): bool {
        $email = $params['email'];
        $token = $params['token'];

        $token_sso = SsoEmailToken::where(['token' => $token, 'email' => $email])->first();
        if (!isset($token_sso)) {
            throw new SSOException("Verification code is not valid", 503);
        }

        #check if this is primary registration object
        $uid = config('sso.config.uid');
        DB::beginTransaction();
        try {
            //if uid is equals to current class name then should we do insert new sso user record
            if ($uid == $this->read_class_name(get_called_class())) {
                $sso_users = $this->add_sso_user_by_email($token_sso->email, $token_sso->password);
                $this->add_app_user($sso_users, $token_sso->password);
            } else {
                $sso_users = $this->update_sso_user($sso_users, $sso_social);
            }
            $token_sso->delete();
            DB::commit();
        } catch (Exception $exc) {
            DB::rollBack();
            throw new SSOException('Unable to create user account', 501);
        }

        $this->sso_session_put(CSSO::$SSO_USERS_KEY, $sso_users);
        $this->sso_session_put('provider', 'email');
        $this->stepDone = true;
        return true;
    }

    public function validate(&$params = array()) {
        $this->stepDone = $this->sso_session_has('provider');
        if (!$this->stepDone) {
            $social_mode = isset($params['social_mode']) ? $params['social_mode'] : '';
            if ($this->email == true && $social_mode == 'email') {
                $req = isset($params['request']) ? $params['request'] : '';
                # if mode email is enabled and current reques tis to login with email
                if ($req == "auth")
                    $this->stepDone = $this->do_username_auth($params);
                elseif ($req == "verify_email")
                    $this->stepDone = $this->do_email_verify($params);
                else
                # if user is trying to create a new account
                    $this->stepDone = $this->do_email_create($params);
            } else {
                # user is trying to sign-in with social
                if (isset($params['provider'])) {
                    if (isset($params['state']) && $params['state'] == 'auth') {
                        $this->stepDone = $this->do_sso($params);
                    } else {
                        $this->stepDone = $this->do_sso_callback($params);
                    }
                } else {
                    return false;
                }
            }
        }
        if ($this->stepDone) {
            if (is_object($this->next))
                return $this->next->validate($params);
        } else
            return $this->stepDone;
    }

    function initForm() {
        return '';
    }

    function getCurrentStep() {
        $this->stepDone = $this->sso_session_has('provider');
        if (!$this->stepDone) {
            return $this->read_class_name(get_called_class());
        } elseif ($this->stepDone == true && is_object($this->next)) {
            return $this->next->getCurrentStep();
        } else {
            return $this->view;
        }
    }

    function send_verification_email($email_token) {
        try {
            Mail::to($email_token->email)->queue(new VerificationMail($email_token->email, $email_token->token));
        } catch (Exception $exc) {
            report($exc);
            throw new Exception("unable to send verification email to" . $email_token->email, 105);
        }
    }

}
