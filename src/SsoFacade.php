<?php

namespace Moontius\SSOService;

use Illuminate\Support\Facades\Facade as BaseFacade;

/**
 * Facade for the SMS provider
 */
class SsoFacade extends BaseFacade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sso';
    }
}
