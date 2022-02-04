<?php

namespace tests\eLife\CiviContacts\Fakes;

use eLife\CiviContacts\Guzzle\CiviCrmClientInterface;
use eLife\CiviContacts\Model\Subscriber;
use eLife\CiviContacts\Providers\AppServiceProvider;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;

final class FakeServiceProvider extends AppServiceProvider
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
                return new class implements CiviCrmClientInterface
                {
                    public function storeSubscriberUrls(Subscriber $subscriber) : PromiseInterface
                    {
                        return Create::promiseFor($subscriber);
                    }

                    public function getAllSubscribers(int $ceiling = 0, int $limit = 100, int $offset = 0) : array
                    {
                        return [
                            1 => new Subscriber(1, 'http://localhost/content-alerts/foo'),
                            2 => new Subscriber(2, null, 'http://localhost/content-alerts/bar'),
                            3 => new Subscriber(3),
                        ];
                    }
                };
            }
        );
    }
}
