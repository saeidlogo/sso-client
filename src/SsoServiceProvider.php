<?php

namespace Moontius\SSOService;

use Illuminate\Support\ServiceProvider;
use Moontius\SSOService\CSSO;

class SsoServiceProvider extends ServiceProvider {

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register() {
        $this->app->bind(CSSO::class, function ($app) {
            $uidObject = config('sso.config.uid');
            $steps = config('sso.config.steps');
            $signOnObject = new CSSO('laravel', $uidObject, null);
            $signOnObject->setSteps($steps);
            return $signOnObject;
        });


        $this->mergeConfigFrom(
                __DIR__ . '/config/config.php', 'sso.php'
        );
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot() {

        require __DIR__ . '/Http/routes.php';

        if ($this->app->runningInConsole()) {
            $this->definePublishing();
        }
    }

    /**
     * Define the publishable migrations and resources.
     *
     * @return void
     */
    protected function definePublishing() {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('sso.php'),
                ], 'config');

        if (!class_exists('CreateSsoSocialsTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/migrations/create_sso_socials_table.php.stub' => $this->app->databasePath() . '/migrations/' . $timestamp . '_create_sso_socials_table.php',
                    ], 'migrations');
        }

        if (!class_exists('CreateSsoUsersTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/migrations/create_sso_users_table.php.stub' => $this->app->databasePath() . '/migrations/' . $timestamp . '_create_sso_users_table.php',
                    ], 'migrations');
        }

        if (!class_exists('CreateSsoUserMapTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/migrations/create_sso_user_map_table.php.stub' => $this->app->databasePath() . '/migrations/' . $timestamp . '_create_sso_user_map_table.php',
                    ], 'migrations');
        }

        if (!class_exists('CreateSsoClientsPhoneOtpsTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/migrations/create_sso_clients_phone_otps_table.php.stub' => $this->app->databasePath() . '/migrations/' . $timestamp . '_create_sso_clients_phone_otps_table.php',
                    ], 'migrations');
        }
        if (!class_exists('CreateSsoClientsPhonesTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/migrations/create_sso_clients_phones_table.php.stub' => $this->app->databasePath() . '/migrations/' . $timestamp . '_create_sso_clients_phones_table.php',
                    ], 'migrations');
        }
        if (!class_exists('CreateSsoSignInOtpsTable')) {
            $timestamp = date('Y_m_d_His', time());

            $this->publishes([
                __DIR__ . '/migrations/create_sso_sign_in_otps_table.php.stub' => $this->app->databasePath() . '/migrations/' . $timestamp . '_create_sso_sign_in_otps_table.php',
                    ], 'migrations');
        }
    }

}
