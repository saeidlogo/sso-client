<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Moontius\SSOService\CSSO;

/**
 * Description of MobileController
 *
 * @author saeidlogo
 */
class SignOnController extends Controller {

    public function signon(Request $request, CSSO $csso) {
        try {
            return $this->process($request, $request->all(), $csso);
        } catch (\Exception $exc) {
            if ($exc instanceof \Moontius\XeroOAuth\Exceptions\XeroException) {
                return redirect()->route('xero.oauth')->withErrors($exc->getMessage());
            }
            return abort(404);
        }
    }

    ## Email base actions

    public function email_signin(Request $request, CSSO $csso) {
        $request->validate([
            'username' => [
                'email',
            ],
            'password' => [
                'required',
                'min:8',
                'max:32',
                'regex:/[A-Z|a-z|0-9]*/s',
            ],
        ]);
        $params = $request->all();
        $params['social_mode'] = 'email';
        $params['request'] = 'auth';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('sso.start')->withErrors($exc->getMessage());
        }
    }

    public function email_signup(Request $request, CSSO $csso) {
        $request->validate([
            'email' => [
                'email',
            ],
            'password' => [
                'required',
                'min:8',
                'max:32',
                'confirmed',
                'regex:/[A-Z|a-z|0-9]*/s',
            ],
        ]);
        $params = $request->all();
        $params['social_mode'] = 'email';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('sso.start')->withErrors($exc->getMessage());
        }
    }

    public function email_verify(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['social_mode'] = 'email';
        $params['request'] = 'verify_email';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('sso.start')->withErrors($exc->getMessage());
        }
    }

    ##++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ## Social base actions

    public function social_signon(Request $request, $provider, CSSO $csso) {
        $params = $request->all();
        $params['provider'] = $provider;
        $params['state'] = 'auth';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return abort(404);
        }
    }

    public function social_callback(Request $request, $provider, CSSO $csso) {
        $params = $request->all();
        $params['provider'] = $provider;
        $params['state'] = 'callback';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            ## should be redirected to correct routing
            return redirect()->route('sso.start')->withErrors($exc->getMessage());
        }
    }

    ##++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ## Mobile base actions

    public function signon_mobile_form(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['verify_state'] = 'mobile_form';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            report($exc);
            return abort(500);
        }
    }

    public function signon_mobile_form_post(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['verify_state'] = 'mobile_submit';
        $errors = $request->session()->has("errors");
        if ($errors) {
            return view("verifiedmobilephone");
        }
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('sso.mobile.form')->withErrors($exc->getMessage());
        }
    }

    public function signon_verify_form(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['verify_state'] = 'verify_form';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            report($exc);
            return abort(500);
        }
    }

    public function signon_verify_submit(Request $request, CSSO $csso) {
        $request->validate([
            'v_code' => [
                'required',
                'regex:/\d{4}/s',
            ],
        ]);
        $params = $request->all();
        $params['verify_state'] = 'verify_submit';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('sso.verify.form')->withErrors($exc->getMessage());
        }
    }

    ## Xero OAuth 
    ##++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    public function xero_oauth(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['xero_state'] = 'xero_oauth_form';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            abort(500);
        }
    }

    public function xero_oauth_authenticate(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['xero_state'] = 'xero_oauth_request';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('xero.oauth')->withErrors($exc->getMessage());
        }
    }

//    public function xero_oauth_logout(OAuthHelper $oauth) {
//        $oauth->logout();
//        return redirect('/');
//    }

    public function xero_oauth_callback(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['xero_state'] = 'xero_oauth_callback';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('xero.oauth')->withErrors($exc->getMessage());
        }
    }

    ## Xero Accounts Mapping 
    ##++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    public function xero_bank_accounts_selection(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['xero_account_state'] = 'xero_accounts_list';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('xero.oauth')->withErrors($exc->getMessage());
        }
    }

    public function xero_bank_accounts_submit(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['xero_account_state'] = 'xero_accounts_submit';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('xero.oauth')->withErrors($exc->getMessage());
        }
    }

    ##++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    public function process(Request $request, $params, CSSO $csso) {
        session_start();
        if ($csso->validate($params)) {
            $currentState = $csso->getCurrentStep();
            $preference = $csso->getViewParams(array($currentState));
            $user = $csso->getUser($currentState);

            if (isset($params['redirect'])) {
                if (isset($params['route'])) {
                    return redirect($params['route']);
                }
                return redirect()->route($currentState);
            }

            return view(strtolower($currentState), ['params' => $params, 'preference' => $preference, 'user' => $user]);
        }

        $currentState = $csso->getCurrentStep();
        if (isset($params['route'])) {
            return redirect()->route($params['route']);
        }

        $preference = $csso->getViewParams(array($currentState));
        $message = isset($params['message']) ? $params['message'] : null;
        return view(strtolower($currentState), ['params' => $params, 'preference' => $preference, 'message' => $message]);
    }

    public function logout(CSSO $csso) {
        if ($csso->logout()) {
            return redirect()->route('sso.start');
        }
    }

    public function profile(Request $request, CSSO $csso) {
        session_start();
        $user = $csso->sso_session_get(CSSO::$SSO_USERS_KEY);
        return view('profile', ['user' => $user]);
    }

}
