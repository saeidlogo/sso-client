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
class WsSignOnController extends Controller {

    public function signon(Request $request, CSSO $csso) {
        try {
            return $this->process($request, $request->all(), $csso);
        } catch (\Exception $exc) {
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
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
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
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
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
        }
    }

    public function email_verify(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['social_mode'] = 'email';
        $params['request'] = 'verify_email';
        try {
            return $this->process($request, $params, $csso);
        } catch (\Exception $exc) {
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
        }
    }

    ##++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ## Social base actions

    public static function social_redirect($url) {
        \Illuminate\Support\Facades\Session::put('url', $url);
    }

    public function do_after_validate(CSSO $csso) {
        $next = $csso->getCurrentStep();
        $preference = $csso->getViewParams(array($next));
        $user = $csso->getUser($next);
        return response()->json([
                    'user' => $user,
                    'preference' => $preference,
                    'current_state' => $next
        ]);
    }

    public function social_signon(Request $request, $provider, CSSO $csso) {
        $params = $request->all();
        $params['provider'] = $provider;
        $params['state'] = 'auth';
        try {
            session_start();
            if ($csso->validate($params)) {
                return $this->do_after_validate($csso);
            }
            $next = $csso->getCurrentStep();
            if (isset($next)) {
                $params['next'] = $next;
            }
            $url = $request->session()->get('url');
            if (isset($url)) {
                $request->session()->remove('url');
                $params['url'] = $url;
            }

            $data = ['params' => $params];
            return response()->json($data, 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Exception $exc) {
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
        }
    }

    public function social_callback(Request $request, $provider, CSSO $csso) {
        $params = $request->all();
        $params['provider'] = $provider;
        $params['state'] = 'callback';
        try {
            session_start();
            if ($csso->validate($params)) {
                return $this->do_after_validate($csso);
            }
            $next = $csso->getCurrentStep();
            if (isset($next)) {
                $params['next'] = $next;
            }
            $data = ['params' => $params];
            return response()->json($data, 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Exception $exc) {
            ## should be redirected to correct routing
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
        }
    }

    ##++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ## Mobile base actions

    public function signon_mobile_form(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['verify_state'] = 'mobile_form';
        try {
            session_start();
            if ($csso->validate($params)) {
                return $this->do_after_validate($csso);
            }

            $data = ['params' => $params];
            return response()->json($data, 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Exception $exc) {
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
        }
    }

    public function signon_mobile_form_post(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['verify_state'] = 'mobile_submit';
        try {
            session_start();
            if ($csso->validate($params)) {
                return $this->do_after_validate($csso);
            }

            $data = ['params' => $params];
            return response()->json($data, 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Exception $exc) {
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
        }
    }

    public function signon_verify_form(Request $request, CSSO $csso) {
        $params = $request->all();
        $params['verify_state'] = 'verify_form';
        try {
            if ($csso->validate($params)) {
                return $this->do_after_validate($csso);
            }

            $data = ['params' => $params];
            return response()->json($data, 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Exception $exc) {
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
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
            if ($csso->validate($params)) {
                return $this->do_after_validate($csso);
            }

            $data = ['params' => $params];
            return response()->json($data, 200, [], JSON_UNESCAPED_SLASHES);
        } catch (\Exception $exc) {
            return response()->json([
                        'code' => $exc->getCode(),
                        'error' => $exc->getMessage()
            ]);
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
            return response()->json([
                        'user' => $user,
                        'preference' => $preference,
                        'current_state' => $currentState
            ]);
        }

        //if is not validate
        $data = $request->session()->get('data');
//        return redirect($data);
        $currentState = $csso->getCurrentStep();
        $preference = $csso->getViewParams(array($currentState));
        $message = isset($params['message']) ? $params['message'] : null;
        return response()->json([
                    'data' => $data,
                    'message' => $message,
                    'preference' => $preference,
                    'current_state' => $currentState
        ]);
    }

    public function logout(CSSO $csso) {
        if ($csso->logout()) {
            return response()->json([
                        'result' => 'success'
            ]);
        }
    }

    public function profile(Request $request, CSSO $csso) {
        session_start();
        $user = $csso->sso_session_get(CSSO::$SSO_USERS_KEY);
        return view('profile', ['user' => $user]);
    }

}
