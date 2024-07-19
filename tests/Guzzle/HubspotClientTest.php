<?php

namespace tests\eLife\CiviContacts\Guzzle;

use eLife\CiviContacts\Etoc\EarlyCareer;
use eLife\CiviContacts\Etoc\ElifeNewsletter;
use eLife\CiviContacts\Etoc\LatestArticles;
use eLife\CiviContacts\Etoc\Newsletter;
use eLife\CiviContacts\Etoc\Subscription;
use eLife\CiviContacts\Guzzle\CiviCrmClientInterface;
use eLife\CiviContacts\Guzzle\HubspotClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use tests\eLife\CiviContacts\TestCase;
use Traversable;

final class HubspotClientTest extends TestCase
{
    /**
     * @test
     */
    public function it_will_check_for_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode([
                'total' => 1,
                'results' => [
                    [
                        'properties' => [
                            'hs_object_id' => '12345',
                            'community_news' => null,
                            'elife_news' => null,
                            'twice_weekly_research_updates' => 'true',
                            'email' => 'foo@bar.com',
                            'firstname' => '',
                            'lastname' => '',
                            'etoc___preference_management_url' => 'http://localhost/content-alerts/foo',
                            'etoc___opt_out' => 'false',
                        ],
                    ],
                ],
            ])),
            new Response(200, [], json_encode(['results' => []])),
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
        $this->assertEquals(json_encode([
            'properties' => [
                'email',
                'firstname',
                'lastname',
                'twice_weekly_research_updates',
                'elife_news',
                'community_news',
                'etoc___preference_management_url',
                'etoc___opt_out',
            ],
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'email',
                            'value' => 'foo@bar.com',
                            'operator' => 'EQ',
                        ],
                    ],
                ],
            ],
        ]), $firstRequest->getBody()->getContents());
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertEquals('/crm/v3/objects/contacts/search', $firstRequest->getUri()->getPath());
        $this->assertEquals(['application/json'], $firstRequest->getHeaders()['Content-Type']);
        $this->assertEquals(['Bearer api-key'], $firstRequest->getHeaders()['Authorization']);

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
            new Response(200, [], json_encode([
                'total' => 1,
                'results' => [
                    [
                        'properties' => [
                            'hs_object_id' => '12345',
                            'community_news' => null,
                            'elife_news' => null,
                            'twice_weekly_research_updates' => 'true',
                            'email' => 'foo@bar.com',
                            'firstname' => '',
                            'lastname' => '',
                            'etoc___preference_management_url' => 'http://localhost/content-alerts/foo',
                            'etoc___opt_out' => null,
                        ],
                    ],
                ],
            ])),
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
        $this->assertEquals([
            'properties' => [
                'email',
                'firstname',
                'lastname',
                'twice_weekly_research_updates',
                'elife_news',
                'community_news',
                'etoc___preference_management_url',
                'etoc___opt_out',
            ],
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'etoc___preference_management_url',
                            'value' => 'http://localhost/content-alerts/foo',
                            'operator' => 'EQ',
                        ],
                    ],
                ],
            ],
        ], json_decode($firstRequest->getBody()->getContents(), true));
        $this->assertEquals('POST', $firstRequest->getMethod());
    }

    /**
     * @test
     * @dataProvider providerQueryFields
     */
    public function it_will_check_for_existing_user_by_url(
        ?Newsletter $newsletter,
        string $expectedQueryField,
        string $otherField = null
    )
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode([
                'total' => 1,
                'results' => [
                    [
                        'properties' => [
                            'hs_object_id' => '12345',
                            'community_news' => 'false',
                            'elife_news' => null,
                            'twice_weekly_research_updates' => 'true',
                            'email' => 'foo@bar.com',
                            'firstname' => '',
                            'lastname' => '',
                            'etoc___preference_management_url' => 'http://localhost/content-alerts/foo',
                            'etoc___opt_out' => 'false',
                        ],
                    ],
                ],
            ])),
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
        $this->assertEquals([
            'properties' => [
                'email',
                'firstname',
                'lastname',
                'twice_weekly_research_updates',
                'elife_news',
                'community_news',
                'etoc___preference_management_url',
                'etoc___opt_out',
            ],
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => $expectedQueryField,
                            'value' => 'http://localhost/content-alerts/foo',
                            'operator' => 'EQ',
                        ],
                    ],
                ],
            ],
        ], json_decode($firstRequest->getBody()->getContents(), true));
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertEquals('/crm/v3/objects/contacts/search', $firstRequest->getUri()->getPath());
        $this->assertEquals(['application/json'], $firstRequest->getHeaders()['Content-Type']);
        $this->assertEquals(['Bearer api-key'], $firstRequest->getHeaders()['Authorization']);
    }

    public function providerQueryFields() : Traversable
    {
        yield 'null' => [null, 'etoc___preference_management_url'];
        yield 'unsubscribe default' => [new LatestArticles(), 'etoc___unsubscribe_url'];
        yield 'unsubscribe early-career' => [new EarlyCareer(), 'etoc___unsubscribe_url'];
        yield 'unsubscribe elife-newsletter' => [new ElifeNewsletter(), 'etoc___unsubscribe_url'];
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

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertEquals('/crm/v3/objects/contacts', $firstRequest->getUri()->getPath());
        $this->assertEquals(['application/json'], $firstRequest->getHeaders()['Content-Type']);
        $this->assertEquals(['Bearer api-key'], $firstRequest->getHeaders()['Authorization']);
        $this->assertEquals([
            'email' => 'email@example.com',
            'firstname' => '',
            'lastname' => '',
            'etoc___preference_management_url' => 'http://localhost/content-alerts/foo',
            'twice_weekly_research_updates' => 'true',
            'etoc___opt_out' => 'false',
        ], json_decode($firstRequest->getBody()->getContents(), true));
    }

    /**
     * @test
     */
    public function it_will_update_preferences_for_an_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => '12345'])),
        ], $container);

        $subscribe = $client->subscribe(
            '12345',
            [
                new LatestArticles(),
                new EarlyCareer(),
            ],
            [
                new LatestArticles(),
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

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('PATCH', $firstRequest->getMethod());
        $this->assertEquals('/crm/v3/objects/contacts/12345', $firstRequest->getUri()->getPath());
        $this->assertEquals(['application/json'], $firstRequest->getHeaders()['Content-Type']);
        $this->assertEquals(['Bearer api-key'], $firstRequest->getHeaders()['Authorization']);
        $this->assertEquals([
            'firstname' => 'New',
            'lastname' => 'Name',
            'etoc___preference_management_url' => 'http://localhost/content-alerts/foo',
            'community_news' => 'true',
            'elife_news' => 'false',
            'etoc___opt_out' => 'false',
        ], json_decode($firstRequest->getBody()->getContents(), true));
    }

    /**
     * @test
     */
    public function it_will_unsubscribe_an_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => '12345'])),
        ], $container);

        $client->unsubscribe('12345', ['community_news'])->wait();

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('PATCH', $firstRequest->getMethod());
        $this->assertEquals('/crm/v3/objects/contacts/12345', $firstRequest->getUri()->getPath());
        $this->assertEquals(['application/json'], $firstRequest->getHeaders()['Content-Type']);
        $this->assertEquals(['Bearer api-key'], $firstRequest->getHeaders()['Authorization']);
        $this->assertEquals([
            'community_news' => 'false',
        ], json_decode($firstRequest->getBody()->getContents(), true));
    }

    /**
     * @test
     */
    public function it_will_optout_an_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => '12345'])),
        ], $container);

        $client->optout(12345, [1,2,3,5], 'reason')->wait();

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('PATCH', $firstRequest->getMethod());
        $this->assertEquals('/crm/v3/objects/contacts/12345', $firstRequest->getUri()->getPath());
        $this->assertEquals(['application/json'], $firstRequest->getHeaders()['Content-Type']);
        $this->assertEquals(['Bearer api-key'], $firstRequest->getHeaders()['Authorization']);
        $this->assertEquals([
            'etoc___opt_out' => 'true',
        ], json_decode($firstRequest->getBody()->getContents(), true));
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

        $this->assertEquals([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(0, $container);
    }

    /**
     * @test
     */
    public function it_will_trigger_preferences_email_setting_preferences_url()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => 12345])),
        ], $container);

        $trigger = $client->triggerPreferencesEmail(12345, 'http://localhost/content-alerts/new-preferences-url');

        $this->assertEquals([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('PATCH', $firstRequest->getMethod());
        $this->assertEquals('/crm/v3/objects/contacts/12345', $firstRequest->getUri()->getPath());
        $this->assertEquals(['application/json'], $firstRequest->getHeaders()['Content-Type']);
        $this->assertEquals(['Bearer api-key'], $firstRequest->getHeaders()['Authorization']);
        $this->assertEquals([
            'etoc___preference_management_url' => 'http://localhost/content-alerts/new-preferences-url',
        ], json_decode($firstRequest->getBody()->getContents(), true));
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

        $trigger = $client->triggerPreferencesEmail(12345);

        $this->assertEquals([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(0, $container);
    }

    private function prepareClient(array $queue = [], array &$container = []) : CiviCrmClientInterface
    {
        $history = Middleware::history($container);

        $mock = new MockHandler($queue);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        return new HubspotClient(new Client(['handler' => $handlerStack]), 'api-key');
    }
}
