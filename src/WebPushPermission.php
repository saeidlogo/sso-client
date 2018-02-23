<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;


/**
 * Description of WebPushPermission
 *
 * @author saeidlogo
 */
class WebPushPermission extends CSSO {

    function __construct($config = null) {
        
    }

    public function validate(&$params) {
        if (is_object($this->next))
            return $this->next->validate($params);
        else
            return true;
    }

    function initForm() {
        return '';
    }

  

}
