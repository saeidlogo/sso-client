<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

/**
 * Description of SSOException
 *
 * @author saeidlogo
 */
class SSOException extends \Exception {

    public static $ERROR_EXCEPTION = 101;
    public static $ERROR_INVALID_MOBILE = 102;
    public static $ERROR_INVALID_TOKEN = 103;
    public static $ERROR_SMS_SEND_EXCEPTION = 105;

    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, Exception $previous = null) {
        // some code
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function customFunction() {
        echo "A custom function for this type of exception\n";
    }

}
