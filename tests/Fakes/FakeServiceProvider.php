<?php

namespace tests\eLife\CiviContacts\Fakes;

use eLife\CiviContacts\Etoc\Newsletter;
use eLife\CiviContacts\Etoc\Subscription;
use eLife\CiviContacts\Guzzle\CiviCrmClientInterface;
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
                    public function storeSubscriberUrls(Subscription $subscription) : PromiseInterface
                    {
                        return Create::promiseFor($subscription);
                    }

                    public function getAllSubscribers(int $total = 0, int $batchSize = 100, int $offset = 0) : array
                    {
                        return [
                            1 => new Subscription(1,
                                null,
                                null,
                                null,
                                null,
                                [],
                                'http://localhost/content-alerts/foo'
                            ),
                            2 => new Subscription(
                                2,
                                null,
                                null,
                                null,
                                null,
                                [],
                                null,
                                'http://localhost/content-alerts/unsubscribe/bar'
                            ),
                            3 => new Subscription(3,
                                null,
                                null,
                                null,
                                null,
                                [],
                                null,
                                null,
                                'http://localhost/content-alerts/optout/baz'
                            ),
                            4 => new Subscription(4),
                            $total + $batchSize + $offset => new Subscription($total + $batchSize + $offset),
                        ];
                    }

                    public function subscribe(
                        string $email,
                        array $preferences,
                        array $newsletters,
                        string $preferencesUrl,
                        string $unsubscribeUrl = null,
                        string $optoutUrl = null,
                        string $firstName = null,
                        string $lastName = null,
                        array $preferencesBefore = null
                    ) : PromiseInterface
                    {
                        return Create::promiseFor(null);
                    }

                    public function unsubscribe(int $contactId, array $groups) : PromiseInterface
                    {
                        return Create::promiseFor(null);
                    }

                    public function optout(
                        int $contactId,
                        array $reasons,
                        string $reasonOther = null
                    ) : PromiseInterface
                    {
                        return Create::promiseFor(null);
                    }

                    public function checkSubscription(
                        string $identifier,
                        bool $isEmail = true,
                        Newsletter $newsletter = null,
                        string $field = null
                    ) : PromiseInterface
                    {
                        return Create::promiseFor(null);
                    }

                    public function triggerPreferencesEmail(
                        int $contactId,
                        string $preferencesUrl = null
                    ) : PromiseInterface
                    {
                        return Create::promiseFor(null);
                    }
                };
            }
        );
    }
}
