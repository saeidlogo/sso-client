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
class UserProfileController extends Controller {

    public function change_password(Request $request) {
        try {
            return view('account.changepassword');
        } catch (\Exception $exc) {
            report($exc);
            return abort(500);
        }
    }

    public function change_password_submit(Request $request, CSSO $csso) {
        try {
            $request->validate([
                'password' => [
                    'required',
                    'min:8',
                    'max:32',
                    'confirmed',
                    'regex:/[A-Z|a-z|0-9]*/s',
                ],
            ]);
            session_start();
            $csso->change_user_password($request->input('password'));
            return view('account.changepassword', ['message' => 'Password has been changed successfully.']);
        } catch (\Exception $exc) {
            return redirect()->route('user.change.password')->withErrors($exc->getMessage());
        }
    }

}
