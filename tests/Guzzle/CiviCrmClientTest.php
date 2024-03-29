<?php

namespace tests\eLife\CiviContacts\Guzzle;

use eLife\CiviContacts\Etoc\EarlyCareer;
use eLife\CiviContacts\Etoc\ElifeNewsletter;
use eLife\CiviContacts\Etoc\LatestArticles;
use eLife\CiviContacts\Etoc\Newsletter;
use eLife\CiviContacts\Etoc\Subscription;
use eLife\CiviContacts\Exception\CiviCrmResponseError;
use eLife\CiviContacts\Guzzle\CiviCrmClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use tests\eLife\CiviContacts\TestCase;
use Traversable;

final class CiviCrmClientTest extends TestCase
{
    /**
     * @test
     */
    public function it_will_check_for_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['values' => [
                [
                    'contact_id' => 12345,
                    'is_opt_out' => '0',
                    'email' => 'foo@bar.com',
                    'first_name' => '',
                    'last_name' => '',
                    'preferences' => [53,435],
                    'groups' => implode(',', [53,435]),
                    'custom_140' => 'http://localhost/content-alerts/foo',
                ],
            ]])),
            new Response(200, [], json_encode(['values' => []])),
        ], $container);

        $checkSuccess = $client->checkSubscription('foo@bar.com');

        $this->assertEquals(new Subscription(
            12345,
            false,
            'foo@bar.com',
            '',
            '',
            [LatestArticles::GROUP_ID],
            'http://localhost/content-alerts/foo'
        ), $checkSuccess->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'get',
            'json' => [
                'email' => 'foo@bar.com',
                'return' => [
                    'group',
                    'first_name',
                    'last_name',
                    'email',
                    'is_opt_out',
                    'custom_140',
                ],
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('GET', $firstRequest->getMethod());

        $checkFail = $client->checkSubscription('http://localhost/content-alerts/foo', false);

        $this->assertNull($checkFail->wait());
    }

    /**
     * @test
     */
    public function it_will_check_for_existing_user_by_preferences_url()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['values' => [
                [
                    'contact_id' => 12345,
                    'is_opt_out' => '0',
                    'email' => 'foo@bar.com',
                    'first_name' => '',
                    'last_name' => '',
                    'preferences' => [53,435],
                    'groups' => implode(',', [53,435]),
                    'custom_140' => 'http://localhost/content-alerts/foo',
                ],
            ]])),
        ], $container);

        $checkSuccess = $client->checkSubscription('http://localhost/content-alerts/foo', false);

        $this->assertEquals(new Subscription(
            12345,
            false,
            'foo@bar.com',
            '',
            '',
            [LatestArticles::GROUP_ID],
            'http://localhost/content-alerts/foo'
        ), $checkSuccess->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'get',
            'json' => [
                'custom_140' => 'http://localhost/content-alerts/foo',
                'return' => [
                    'group',
                    'first_name',
                    'last_name',
                    'email',
                    'is_opt_out',
                    'custom_140',
                ],
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('GET', $firstRequest->getMethod());
    }

    /**
     * @test
     * @dataProvider providerQueryFields
     */
    public function it_will_check_for_existing_user_by_url(?Newsletter $newsletter, string $expectedQueryField, string $otherField = null)
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['values' => [
                [
                    'contact_id' => 12345,
                    'is_opt_out' => '0',
                    'email' => 'foo@bar.com',
                    'first_name' => '',
                    'last_name' => '',
                    'preferences' => [53,435],
                    'groups' => implode(',', [53,435]),
                    'custom_140' => 'http://localhost/content-alerts/foo',
                ],
            ]])),
        ], $container);

        $checkSuccess = $client->checkSubscription(
            'http://localhost/content-alerts/foo',
            false,
            $newsletter,
            $otherField
        );

        $this->assertEquals(new Subscription(
            12345,
            false,
            'foo@bar.com',
            '',
            '',
            [LatestArticles::GROUP_ID],
            'http://localhost/content-alerts/foo'
        ), $checkSuccess->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'get',
            'json' => [
                $expectedQueryField => 'http://localhost/content-alerts/foo',
                'return' => [
                    'group',
                    'first_name',
                    'last_name',
                    'email',
                    'is_opt_out',
                    'custom_140',
                ],
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('GET', $firstRequest->getMethod());
    }

    public function providerQueryFields() : Traversable
    {
        yield 'null' => [null, 'custom_140'];
        yield 'unsubscribe default' => [new LatestArticles(), 'custom_138'];
        yield 'unsubscribe early-career' => [new EarlyCareer(), 'custom_138'];
        yield 'unsubscribe elife-newsletter' => [new ElifeNewsletter(), 'custom_138'];
        yield 'other field' => [null, 'other', 'other'];
    }

    /**
     * @test
     */
    public function it_will_subscribe_a_new_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => '12345'])),
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $subscribe = $client->subscribe(
            'email@example.com',
            [
                new LatestArticles(),
            ],
            [],
            'http://localhost/content-alerts/foo'
        );

        $this->assertEquals([
            'contact_id' => '12345',
            'groups' => [
                'added' => ['latest_articles'],
                'removed' => [],
                'unchanged' => [],
            ],
        ], $subscribe->wait());

        $this->assertCount(2, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_type' => 'Individual',
                'email' => 'email@example.com',
                'first_name' => '',
                'last_name' => '',
                'custom_140' => 'http://localhost/content-alerts/foo',
                'is_opt_out' => 0,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $secondRequest */
        $secondRequest = $container[1]['request'];
        $this->assertEquals('POST', $secondRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'status' => 'Added',
                'group_id' => [
                    'All_Content_53',
                    'Journal_eToc_signup_1922',
                ],
                'contact_id' => '12345',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $secondRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_unsubscribe_an_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $unsubscribe = $client->unsubscribe('12345', ['Early_Careers_Scientists_134']);

        $this->assertEquals([
            'is_error' => 0
        ], $unsubscribe->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'status' => 'Removed',
                'group_id' => ['Early_Careers_Scientists_134'],
                'contact_id' => '12345',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_optout_an_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => '12345'])),
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $optout = $client->optout(12345, [1,2,3,5], 'reason');

        $this->assertEquals([
            'is_error' => 0
        ], $optout->wait());

        $this->assertCount(2, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'group_id' => [
                    'Journal_eToc_opt_out_2058',
                ],
                'contact_id' => 12345,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $secondRequest */
        $secondRequest = $container[1]['request'];
        $this->assertEquals('POST', $secondRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_id' => 12345,
                'is_opt_out' => 1,
                'custom_98' => date('Y-m-d'),
                'custom_99' => [1,2,3,5],
                'custom_101' => 'reason',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $secondRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_update_preferences_for_an_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => '12345'])),
            new Response(200, [], json_encode(['is_error' => 0])),
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $subscribe = $client->subscribe(
            '12345',
            [
                new LatestArticles(),
                new EarlyCareer(),
            ],
            [
                new LatestArticles('http://localhost/content-alerts/unsubscribe/foo'),
            ],
            'http://localhost/content-alerts/foo',
            null,
            null,
            'New',
            'Name',
            [
                new LatestArticles(),
                new ElifeNewsletter(),
            ]
        );

        $this->assertEquals([
            'contact_id' => '12345',
            'groups' => [
                'added' => ['early_career'],
                'removed' => ['elife_newsletter'],
                'unchanged' => ['latest_articles'],
            ],
        ], $subscribe->wait());

        $this->assertCount(3, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_type' => 'Individual',
                'contact_id' => '12345',
                'first_name' => 'New',
                'last_name' => 'Name',
                'custom_140' => 'http://localhost/content-alerts/foo',
                'is_opt_out' => 0,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $secondRequest */
        $secondRequest = $container[1]['request'];
        $this->assertEquals('POST', $secondRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'status' => 'Added',
                'group_id' => [
                    'Early_Careers_Scientists_134',
                ],
                'contact_id' => '12345',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $secondRequest->getUri()->getQuery());

        /** @var Request $thirdRequest */
        $thirdRequest = $container[2]['request'];
        $this->assertEquals('POST', $thirdRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'status' => 'Removed',
                'group_id' => [
                    'eLife_bi_monthly_news_1032',
                ],
                'contact_id' => '12345',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $thirdRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_trigger_preferences_email()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $trigger = $client->triggerPreferencesEmail(12345);

        $this->assertSame([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'group_id' => [
                    'Journal_eToc_preferences_1923',
                ],
                'contact_id' => 12345,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_trigger_preferences_email_setting_preferences_url()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => 12345])),
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $trigger = $client->triggerPreferencesEmail(12345, 'http://localhost/content-alerts/new-preferences-url');

        $this->assertSame([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(2, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_id' => 12345,
                'custom_140' => 'http://localhost/content-alerts/new-preferences-url',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $secondRequest */
        $secondRequest = $container[1]['request'];
        $this->assertEquals('POST', $secondRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'group_id' => [
                    'Journal_eToc_preferences_1923',
                ],
                'contact_id' => 12345,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $secondRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_trigger_unsubscribe_confirmation_email()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $trigger = $client->triggerUnsubscribeEmail(12345);

        $this->assertSame([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'group_id' => [
                    'Journal_eToc_unsubscribe_2055',
                ],
                'contact_id' => 12345,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_store_subscriber_urls()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode([
                'id' => '1',
            ])),
        ], $container);

        $store = $client->storeSubscriberUrls(
            Subscription::urlsOnly(
                1,
                'http://localhost/content-alerts/foo',
                'http://localhost/content-alerts/unsubscribe/bar',
                'http://localhost/content-alerts/optout/baz'
            )
        );

        $this->assertEquals(
            Subscription::urlsOnly(
                1,

                'http://localhost/content-alerts/foo',
                'http://localhost/content-alerts/unsubscribe/bar',
                'http://localhost/content-alerts/optout/baz'
            ),
            $store->wait()
        );

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_id' => 1,
                'custom_140' => 'http://localhost/content-alerts/foo',
                'custom_138' => 'http://localhost/content-alerts/unsubscribe/bar',
                'custom_139' => 'http://localhost/content-alerts/optout/baz',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_get_all_subscribers()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode([
                'values' => [
                    [
                        'id' => 1,
                        'custom_140' => 'http://localhost/content-alerts/foo',
                        'custom_138' => '',
                        'custom_139' => '',
                    ],
                    [
                        'id' => 2,
                        'custom_140' => '',
                        'custom_138' => 'http://localhost/content-alerts/unsubscribe/bar',
                        'custom_139' => '',
                    ],
                    [
                        'id' => 3,
                        'custom_140' => '',
                        'custom_138' => '',
                        'custom_139' => 'http://localhost/content-alerts/optout/baz',
                    ],
                    [
                        'id' => 4,
                        'custom_140' => '',
                        'custom_138' => '',
                        'custom_139' => '',
                    ],
                ],
            ])),
        ], $container);

        $subscribers = $client->getAllSubscribers(13, 10, 1);

        $this->assertEquals([
            Subscription::urlsOnly(1, 'http://localhost/content-alerts/foo'),
            Subscription::urlsOnly(2, '', 'http://localhost/content-alerts/unsubscribe/bar'),
            Subscription::urlsOnly(3, '', '', 'http://localhost/content-alerts/optout/baz'),
            Subscription::urlsOnly(4),
        ], $subscribers);
        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'get',
            'json' => [
                'return' => [
                    'id',
                    'custom_140',
                    'custom_138',
                    'custom_139',
                ],
                'group' => [
                    'All_Content_53',
                    'Early_Careers_Scientists_134',
                    'eLife_bi_monthly_news_1032',
                ],
                'custom_139' => ['IS NULL' => 1],
                'is_opt_out' => 0,
                'options' => [
                    'limit' => 10,
                    'offset' => 1,
                ],
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_can_handle_errors()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode([
                'is_error' => 1,
                'error_message' => 'Civi is broken!',
            ])),
        ], $container);

        $this->expectException(CiviCrmResponseError::class);
        $this->expectExceptionMessage('Civi is broken!');

        $client->storeSubscriberUrls(new Subscription(1))->wait();
    }

    private function prepareClient(array $queue = [], array &$container = []) : CiviCrmClient
    {
        $history = Middleware::history($container);

        $mock = new MockHandler($queue);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        return new CiviCrmClient(new Client(['handler' => $handlerStack]), 'api-key', 'site-key');
    }

    private function prepareQuery(array $query) : string
    {
        return str_replace('+', '%20', http_build_query(array_map(function ($value) {
            return is_array($value) ? json_encode($value) : $value;
        }, $query)));
    }
}
