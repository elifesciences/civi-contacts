<?php

namespace tests\eLife\CiviContacts\Guzzle;

use eLife\CiviContacts\Exception\CiviCrmResponseError;
use eLife\CiviContacts\Guzzle\CiviCrmClient;
use eLife\CiviContacts\Model\Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use tests\eLife\CiviContacts\TestCase;

final class CiviCrmClientTest extends TestCase
{
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
            new Subscriber(
                1,
                'http://localhost/content-alerts/foo',
                'http://localhost/content-alerts/unsubscribe/bar',
                'http://localhost/content-alerts/optout/baz'
            )
        );

        $this->assertEquals(new Subscriber(1, 'http://localhost/content-alerts/foo', 'http://localhost/content-alerts/unsubscribe/bar', 'http://localhost/content-alerts/optout/baz'), $store->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_id' => 1,
                'custom_131' => 'http://localhost/content-alerts/foo',
                'custom_132' => 'http://localhost/content-alerts/unsubscribe/bar',
                'custom_136' => 'http://localhost/content-alerts/optout/baz',
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
        $this->markTestSkipped('Skip for now');

        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode([
                'values' => [
                    [
                        'id' => 1,
                        'custom_131' => 'http://localhost/content-alerts/foo',
                        'custom_132' => '',
                        'custom_136' => '',
                    ],
                    [
                        'id' => 2,
                        'custom_131' => '',
                        'custom_132' => 'http://localhost/content-alerts/unsubscribe/bar',
                        'custom_136' => '',
                    ],
                    [
                        'id' => 3,
                        'custom_131' => '',
                        'custom_132' => '',
                        'custom_136' => 'http://localhost/content-alerts/optout/baz',
                    ],
                    [
                        'id' => 4,
                        'custom_131' => '',
                        'custom_132' => '',
                        'custom_136' => '',
                    ],
                ],
            ])),
        ], $container);

        $subscribers = $client->getAllSubscribers(13, 10, 1);

        $this->assertEquals([
            new Subscriber(1, 'http://localhost/content-alerts/foo'),
            new Subscriber(2, '', 'http://localhost/content-alerts/unsubscribe/bar'),
            new Subscriber(3, '', '', 'http://localhost/content-alerts/optout/baz'),
            new Subscriber(4),
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
                    'custom_131',
                    'custom_132',
                ],
                'group' => [
                    'All_Content_53',
                    'early_careers_news_317',
                    'technology_news_435',
                    'eLife_bi_monthly_news_1032',
                ],
                'custom_132' => ['IS NULL' => 1],
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

        $client->storeSubscriberUrls(new Subscriber(1))->wait();
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
        return http_build_query(array_map(function ($value) {
            return is_array($value) ? json_encode($value) : $value;
        }, $query));
    }
}
