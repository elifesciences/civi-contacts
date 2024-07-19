<?php

namespace eLife\CiviContacts\Guzzle;

use eLife\CiviContacts\Etoc\EarlyCareer;
use eLife\CiviContacts\Etoc\ElifeNewsletter;
use eLife\CiviContacts\Etoc\LatestArticles;
use eLife\CiviContacts\Etoc\Newsletter;
use eLife\CiviContacts\Etoc\Subscription;
use eLife\CiviContacts\Exception\HubspotResponseError;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class HubspotClient implements CiviCrmClientInterface
{
    // Custom field to store user preferences link to be included in emails.
    const FIELD_PREFERENCES_URL = 'etoc___preference_management_url';
    // Custom field to store unsubscribe link to be included in emails.
    const FIELD_UNSUBSCRIBE_URL = 'etoc___unsubscribe_url';
    // Custom field to store opt-out link to be included in emails.
    const FIELD_OPTOUT_URL = 'etoc___opt_out_url';
    // Custom field to store opt-out
    const FIELD_OUTPUT = 'etoc___opt_out';

    private $client;
    private $apiKey;

    public function __construct(ClientInterface $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    private function storePreferencesUrl(int $contactId, string $preferencesUrl) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('PATCH', '/crm/v3/objects/contacts/' . $contactId), [
            'body' => json_encode([
                self::FIELD_PREFERENCES_URL => $preferencesUrl,
            ]),
        ])->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function ($data) {
            return ['contact_id' => $data['id']];
        });
    }

    public function optout(int $contactId, array $reasons = [], string $reasonOther = null) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('PATCH', '/crm/v3/objects/contacts/' . $contactId), [
            'body' => json_encode([
                self::FIELD_OUTPUT => 'true',
            ]),
        ])->then(function (Response $response) {
            return $this->prepareResponse($response);
        });
    }

    public function unsubscribe(int $contactId, array $groups) : PromiseInterface
    {
        $body = [];

        foreach ($groups as $group) {
            $body[$group] = 'false';
        }

        return $this->client->sendAsync($this->prepareRequest('PATCH', '/crm/v3/objects/contacts/' . $contactId), [
            'body' => json_encode($body),
        ])->then(function (Response $response) {
            return $this->prepareResponse($response);
        });
    }

    /**
     * @param Newsletter[] $preferences
     * @param Newsletter[] $newsletters
     * @param null|Newsletter[] $preferencesBefore
     */
    public function subscribe(
        string $identifier,
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
        /** @var Newsletter[] $add */
        $add = array_values(array_diff($preferences, $preferencesBefore ?? []));
        /** @var Newsletter[] $remove */
        $remove = array_values(array_diff($preferencesBefore ?? [], $preferences));

        $options = [
            'firstname' => $firstName ?? '',
            'lastname' => $lastName ?? '',
            self::FIELD_PREFERENCES_URL => $preferencesUrl,
            self::FIELD_OUTPUT => 'false',
        ];

        foreach ($add as $a) {
            $options[$a->groupId()] = 'true';
        }

        foreach ($remove as $r) {
            $options[$r->groupId()] = 'false';
        }

        return $this->client->sendAsync(
            $this->prepareRequest(
                is_null($preferencesBefore) ? 'POST' : 'PATCH',
                '/crm/v3/objects/contacts' . (!is_null($preferencesBefore) ? '/' . $identifier : '')
            ),
            ['body' => json_encode($options +
            (
                is_null($preferencesBefore) ? [
                    'email' => $identifier,
                ] : []
            ) +
            (
                $unsubscribeUrl ? [
                    self::FIELD_UNSUBSCRIBE_URL => $unsubscribeUrl,
                ] : []
            ) +
            (
                $optoutUrl ? [
                    self::FIELD_OPTOUT_URL => $optoutUrl,
                ] : []
            ))]
        )->then(function (Response $response) use ($preferences, $preferencesBefore) {
            $data = $this->prepareResponse($response);
            $add = array_values(array_diff($preferences, $preferencesBefore ?? []));
            $remove = array_values(array_diff($preferencesBefore ?? [], $preferences));
            $unchanged = array_diff($preferencesBefore ?? [], $add, $remove);

            return [
                'contact_id' => $data['id'],
                'groups' => [
                    'added' => $add,
                    'removed' => $remove,
                    'unchanged' => $unchanged,
                ],
            ];
        });
    }

    public function checkSubscription(
        string $identifier,
        bool $isEmail = true,
        Newsletter $newsletter = null,
        string $field = null
    ) : PromiseInterface
    {
        return $this->client->sendAsync(
            $this->prepareRequest('POST', '/crm/v3/objects/contacts/search'),
            [
                'body' => json_encode([
                    'properties' => [
                        'email',
                        'firstname',
                        'lastname',
                        'twice_weekly_research_updates',
                        'elife_news',
                        'community_news',
                        self::FIELD_PREFERENCES_URL,
                        self::FIELD_OUTPUT,
                    ],
                    'filterGroups' => [
                        [
                            'filters' => [
                                [
                                    'propertyName' =>
                                        $isEmail ?
                                            'email' :
                                            (
                                                $newsletter ?
                                                    self::FIELD_UNSUBSCRIBE_URL :
                                                    ($field ?? self::FIELD_PREFERENCES_URL)
                                            ),
                                    'value' => $identifier,
                                    'operator' => 'EQ',
                                ],
                            ],
                        ],
                    ],
                ]),
            ]
        )->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function ($data) {
            if ($results = $data['results']) {
                $contact = $results[0]['properties'];
                return new Subscription(
                    (int) $contact['hs_object_id'],
                    ('true' === $contact[self::FIELD_OUTPUT]),
                    $contact['email'],
                    $contact['firstname'],
                    $contact['lastname'],
                    array_filter(array_map(function ($group) use ($contact) {
                        return !empty($contact[$group]) && 'true' === $contact[$group] ? $group : null;
                    }, [
                        LatestArticles::GROUP_ID,
                        ElifeNewsletter::GROUP_ID,
                        EarlyCareer::GROUP_ID,
                    ])),
                    $contact[self::FIELD_PREFERENCES_URL]
                );
            }

            return null;
        });
    }

    public function triggerPreferencesEmail(int $contactId, string $preferencesUrl = null) : PromiseInterface
    {
        if ($preferencesUrl) {
            return self::storePreferencesUrl($contactId, $preferencesUrl)
                ->then(function ($data) {
                    return self::triggerPreferencesEmail($data['contact_id']);
                });
        }

        // @todo - trigger email to contact with preferences URL.
        $promise = new Promise();
        $promise->resolve([
            'contact_id' => $contactId,
        ]);

        return $promise;
    }

    public function triggerUnsubscribeEmail(int $contactId) : PromiseInterface
    {
        // @todo - trigger email to contact with unsubscribe URL.
        $promise = new Promise();
        $promise->resolve([
            'contact_id' => $contactId,
        ]);

        return $promise;
    }

    public function storeSubscriberUrls(Subscription $subscription) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'Contact',
                'action' => 'create',
                'json' => [
                    'contact_id' => $subscription->getId(),
                    self::FIELD_PREFERENCES_URL => $subscription->getPreferencesUrl(),
                    self::FIELD_UNSUBSCRIBE_URL => $subscription->getUnsubscribeUrl(),
                    self::FIELD_OPTOUT_URL => $subscription->getOptoutUrl(),
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function (array $data) use ($subscription) {
            return Subscription::urlsOnly(
                (int) $data['id'],
                $subscription->getPreferencesUrl(),
                $subscription->getUnsubscribeUrl(),
                $subscription->getOptoutUrl()
            );
        });
    }

    public function getAllSubscribers(int $total = 0, int $batchSize = 100, int $offset = 0) : array
    {
        $allSubscribers = [];
        while ((0 === $total || ($batchSize + $offset) <= $total)) {
            try {
                $subscribers = $this->getSubscribers($batchSize, $offset)->wait();
            } catch (RequestException $exception) {
                throw new Exception(implode(',', $allSubscribers), 0, $exception);
            }

            $offset += $batchSize;
            $allSubscribers = $subscribers + $allSubscribers;
        }

        return $allSubscribers;
    }

    private function getSubscribers($limit = 100, $offset = 0) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('GET'), $this->options([
            'query' => [
                'entity' => 'Contact',
                'action' => 'get',
                'json' => [
                    'return' => [
                        'id',
                        self::FIELD_PREFERENCES_URL,
                        self::FIELD_UNSUBSCRIBE_URL,
                        self::FIELD_OPTOUT_URL,
                    ],
                    'group' => [
                        LatestArticles::GROUP,
                        EarlyCareer::GROUP,
                        ElifeNewsletter::GROUP,
                    ],
                    self::FIELD_OPTOUT_URL => ['IS NULL' => 1],
                    'is_opt_out' => 0,
                    'options' => [
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function (array $response) {
            return array_map(function ($contact) {
                return Subscription::urlsOnly(
                    (int) $contact['id'],
                    $contact[self::FIELD_PREFERENCES_URL],
                    $contact[self::FIELD_UNSUBSCRIBE_URL],
                    $contact[self::FIELD_OPTOUT_URL]
                );
            }, $response['values']) ?? [];
        });
    }

    private function prepareRequest(string $method, string $uri = '', array $headers = []) : Request
    {
        return new Request(
            $method,
            $uri,
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey
            ] + $headers
        );
    }

    private function options(array $options = []) : array
    {
        $options['query'] = array_map(function ($param) {
            return is_array($param) ? json_encode($param) : $param;
        }, array_merge($options['query'] ?? [], array_filter(['api_key' => $this->apiKey])));

        return $options;
    }

    /**
     * @param Response $response
     * @return mixed
     * @throws HubspotResponseError
     */
    private function prepareResponse(Response $response) : array
    {
        $body = json_decode($response->getBody()->getContents(), true);

        if (!empty($body['is_error'])) {
            throw new HubspotResponseError($body['error_message'], $response);
        }

        return $body;
    }
}
