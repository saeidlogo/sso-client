<?php

namespace Moontius\SSOService;

use Illuminate\Support\ServiceProvider;

class HybridAuthProvider extends ServiceProvider {

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot() {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register() {
        $this->app->bind('Hybridauth', function ($app, $params) {
            $auth = config('sso.config');
            $callback = isset($params['callback']) ? $params['callback'] : 'ws_callback';
            $auth['callback'] = str_replace('{provider}', $params['provider'], $auth[$callback]);
            $hybridauth = new \Hybridauth\Hybridauth($auth);
            return $hybridauth;
        });
    }

}
