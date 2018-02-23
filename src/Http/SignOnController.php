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
            return abort(404);
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
        $params = $request->all();
        $params['verify_state'] = 'verify_submit';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return redirect()->route('sso.verify.form')->withErrors($exc->getMessage());
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
                return redirect()->route($currentState, ['params' => $params, 'preference' => $preference, 'user' => $user]);
            }

            return view(strtolower($currentState), ['params' => $params, 'preference' => $preference, 'user' => $user]);
        }

        $currentState = $csso->getCurrentStep();
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
