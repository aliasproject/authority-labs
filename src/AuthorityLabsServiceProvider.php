<?php namespace AliasProject\AuthorityLabs;

use Illuminate\Support\ServiceProvider;

class AuthorityLabsServiceProvider extends ServiceProvider {

   /**
     * Bootstrap the application services.
     *
     * @return void
    */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/authoritylabs.php' => config_path('authoritylabs.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
    */
    public function register()
    {
        //
    }
}
