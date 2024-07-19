<?php

namespace eLife\CiviContacts\Providers;

use eLife\CiviContacts\Guzzle\CiviCrmClientInterface;
use eLife\CiviContacts\Guzzle\HubspotClient;
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
                return new HubspotClient(
                    new Client(config('hubspotclient')),
                    env('HUBSPOT_API_KEY')
                );
            }
        );
    }
}
