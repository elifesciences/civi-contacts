<?php

namespace eLife\CiviContacts\Providers;

use eLife\CiviContacts\Guzzle\CiviCrmClient;
use eLife\CiviContacts\Guzzle\CiviCrmClientInterface;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() : void
    {
        $this->app->bind(
            CiviCrmClientInterface::class,
            function () {
                return new CiviCrmClient(
                    new Client(config('civiclient')),
                    env('CIVI_API_KEY'),
                    env('CIVI_SITE_KEY')
                );
            }
        );
    }
}
