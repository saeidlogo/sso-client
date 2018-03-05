<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

use Moontius\XeroOAuth\Exceptions\XeroException;

/**
 * Description of XeroOAuth
 *
 * @author saeidlogo
 */
class XeroOAuth extends CSSO {

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

    public function validate(&$params) {
        $this->stepDone = \Moontius\XeroOAuth\Facades\XeroOAuth::xero_auth();
        $state = isset($params['xero_state']) ? $params['xero_state'] : 'xero_oauth_form';
        if (!$this->stepDone) {
            switch ($state) {
                case 'xero_oauth_form':
                    if (isset($params['redirect'])) {
                        $params['route'] = $this->view;
                    }
                    break;
                case 'xero_oauth_request':
                    $redirect = \Moontius\XeroOAuth\Facades\XeroOAuth::authenticate();
                    $params['redirect'] = true;
                    $params['route'] = $redirect;
                    return true;
                case 'xero_oauth_callback':
                    $status = \Moontius\XeroOAuth\Facades\XeroOAuth::callback($params);
                    if ($status == 'success') {
                        $params['xero_oauth'] = true;
                        $params['message'] = 'You have been successfully logged In Your Xero Account, please click next to proceed boarding';
                        $this->stepDone=true;
                        $params['redirect'] = true;
                    } else {
                        throw new XeroException('Unable to authenticate with your xero account you need this step to continue boarding steps', 501);
                    }
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

    function initForm() {
        return '';
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
        if (\Moontius\XeroOAuth\Facades\XeroOAuth::xero_auth()) {
            $this->stepDone = true;
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

}
