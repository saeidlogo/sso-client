<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Moontius\SSOService;

/**
 * Description of ISSO
 *
 * @author saeidlogo
 */
interface ICSSO {
    # set array of steps in string to define order of steps and options

    function setSteps($steps = []);

    # return an array of string in order of steps

    function getSteps();

    # get current step in string

    function getCurrentStep();

    # return an array of parameters step=>array

    function getViewParams($step);

    # initialize form 

    function initForm();

    # process input from front-end

    function validate(&$params);
    
    function getUser(&$params);
}
