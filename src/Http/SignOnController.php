<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moonitus\SSOService\Http;

use Illuminate\Http\Request;
use Moonitus\SSOService\SSOException;
use Moonitus\SSOService\CSSO;
use Illuminate\Routing\Controller;

/**
 * Description of MobileController
 *
 * @author saeidlogo
 */
class SignOnController extends Controller {

    public function signon(Request $request, CSSO $csso) {
        return $this->do_after_job($request->all(), $csso);
    }

    public function social_signon(Request $request, $provider, CSSO $csso) {
        $params = $request->all();
        $params['provider'] = $provider;
        $params['state'] = 'auth';
        return $this->do_after_job($params, $csso);
    }

    public function social_callback(Request $request, $provider, CSSO $csso) {
        $params = $request->all();
        $params['provider'] = $provider;
        $params['state'] = 'callback';
        return $this->do_after_job($params, $csso);
    }

    private function do_after_job($params, CSSO $csso) {
        $message = '';
        try {
            $currentState = $csso->getCurrentStep();
            if ($csso->validate($params)) {
                $params = $csso->getViewParams(array($currentState));
                $user = $csso->getUser($currentState);
                return view(strtolower($currentState), ['params' => $params, 'user' => $user]);
            }
            return view(strtolower($currentState), ['params' => $params]);
        } catch (SSOException $ex) {
            $code = $ex->getCode();
            $message = $ex->getMessage();
        }
        return view(strtolower($currentState), ['error' => $message]);
    }

}
