<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

/**
 * Description of XeroOAuth
 *
 * @author saeidlogo
 */
use Moontius\XeroOAuth\Facades\XeroOAuth;
use App\Bank;
use App\User;

class XeroBankMapping extends CSSO {

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
        $this->stepDone = XeroOAuth::xero_session_has('xero_accounts');
        if ($this->stepDone) {
            if (isset($params['redirect'])) {
                $params['route'] = $this->view;
            }
            return true;
        }

        $user = $this->get_app_user();
        $user_id = $user->id;
        $user_obj = User::find($user_id);

        $state = isset($params['xero_account_state']) ? $params['xero_account_state'] : 'xero_accounts_list';
        switch ($state) {
            case 'xero_accounts_list':
                $xero_accounts = XeroOAuth::bank_accounts();
                $banks = Bank::where('enabled', 1)->pluck('name', 'id')->all();
                $this->stepDone = true;
                if (isset($xero_accounts)) {
                    $index = 0;
                    foreach ($xero_accounts as $account) {
                        $account_id = $account->AccountID;
                        $xero_bank = $user_obj->xero_banks()->where('account_id', $account_id)->first();
                        if (!isset($xero_bank)) {
                            if ($this->stepDone) {
                                $this->stepDone = false;
                            }
                            $xero_bank = $this->create_new_xero_acocunt($account);
                            $xero_bank->user_id = $user_id;
                            $xero_bank->save();
                        } else if (!isset($xero_bank->bank_id) && $this->stepDone) {
                            $this->stepDone = false;
                        }
                        $accounts[$index++] = $this->create_account($xero_bank);
                    }
                }

                if (!$this->stepDone) {
                    $params['banks'] = $banks;
                    $params['accounts'] = $accounts;
                }
                
                if (isset($params['redirect'])) {
                    $params['route'] = $this->view;
                }
                break;
            case 'xero_accounts_submit':
                $banks = $user_obj->xero_banks()->get();
                foreach ($banks as $bank) {
                    $key = 'bank_' . $bank->code;
                    $value = isset($params[$key]) ? (int) $params[$key] : 0;
                    if ($value > 0) {
                        $bank->bank_id = $value;
                        $bank->save();
                    }
                }
                $this->stepDone = true;
                break;
        }

        if ($this->stepDone) {
            XeroOAuth::xero_session_put('xero_accounts', true);
            if (is_object($this->next))
                return $this->next->validate($params);
            return true;
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

    public function create_new_xero_acocunt($account) {
        $xero_bank = new \App\XeroBank();
        $xero_bank->account_id = $account->AccountID;
        $xero_bank->account_number = $account->BankAccountNumber;
        $xero_bank->name = $account->Name;
        $xero_bank->code = $account->Code;
        $xero_bank->status = $account->Status == 'Active' ? 1 : 0;
        $xero_bank->currency = $account->CurrencyCode;
        return $xero_bank;
    }

    public function create_account($xero_bank) {
        $bank = $xero_bank->bank;
        $account = new \App\Account();
        $account->account_id = $xero_bank->account_id;
        $account->code = $xero_bank->code;
        $account->name = $xero_bank->name . " (" . $xero_bank->account_number . ")";
        if (isset($bank->id))
            $account->bank = $bank->id;
        return $account;
    }

}
