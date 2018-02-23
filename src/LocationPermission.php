<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

/**
 * Description of LocationPermission
 *
 * @author saeidlogo
 */
class LocationPermission extends CSSO {

    function __construct($config = null) {
        $this->config = $config;
    }

    public function validate(&$params) {
        #throw new Exception("Location Failed");
        if (is_object($this->next))
            return $this->next->validate($params);
        else {
            #return true;
        }
        $this->stage = 'locationPermission';

        return ['stage' => 'askLocationPermission'];
    }

    function initForm() {
        return '';
    }

}
