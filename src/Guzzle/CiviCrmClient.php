<?php

namespace eLife\CiviContacts\Guzzle;

use eLife\CiviContacts\Etoc\EarlyCareer;
use eLife\CiviContacts\Etoc\ElifeNewsletter;
use eLife\CiviContacts\Etoc\LatestArticles;
use eLife\CiviContacts\Etoc\Newsletter;
use eLife\CiviContacts\Etoc\Subscription;
use eLife\CiviContacts\Exception\CiviCrmResponseError;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class CiviCrmClient implements CiviCrmClientInterface
{
    // Assign all users to below group so we can easily identify them.
    const GROUP_JOURNAL_ETOC_SIGNUP = 'Journal_eToc_signup_1922';
    // Add the contact to the below group to trigger email with user preferences link.
    const GROUP_JOURNAL_ETOC_PREFERENCES = 'Journal_eToc_preferences_1923';
    // Add the contact to the below group to trigger email with unsubscribe confirmation.
    const GROUP_JOURNAL_ETOC_UNSUBSCRIBE = 'Journal_eToc_unsubscribe_2055';
    // Add the contact to the below group to trigger email with opt-out confirmation.
    const GROUP_JOURNAL_ETOC_OPTOUT = 'Journal_eToc_opt_out_2058';
    // Custom field to store user preferences link to be included in emails.
    const FIELD_PREFERENCES_URL = 'custom_140';
    // Custom field to store unsubscribe link to be included in emails.
    const FIELD_UNSUBSCRIBE_URL = 'custom_138';
    // Custom field to store opt-out link to be included in emails.
    const FIELD_OPTOUT_URL = 'custom_139';
    // Custom field to store opt-out date.
    const FIELD_OPTOUT_DATE = 'custom_98';
    // Custom field to store opt-out reason.
    const FIELD_OPTOUT_REASON = 'custom_99';
    // Custom field to store opt-out reason (other).
    const FIELD_OPTOUT_REASON_OTHER = 'custom_101';

    private $client;
    private $apiKey;
    private $siteKey;

    public function __construct(ClientInterface $client, string $apiKey, string $siteKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->siteKey = $siteKey;
    }

    private function storePreferencesUrl(int $contactId, string $preferencesUrl) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'Contact',
                'action' => 'create',
                'json' => [
                    'contact_id' => $contactId,
                    self::FIELD_PREFERENCES_URL => $preferencesUrl,
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function ($data) {
            return ['contact_id' => $data['id']];
        });
    }

    public function optout(int $contactId, array $reasons = [], string $reasonOther = null) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'GroupContact',
                'action' => 'create',
                'json' => [
                    'group_id' => [
                        self::GROUP_JOURNAL_ETOC_OPTOUT,
                    ],
                    'contact_id' => $contactId,
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function () use ($contactId, $reasons, $reasonOther) {
            return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
                'query' => [
                    'entity' => 'Contact',
                    'action' => 'create',
                    'json' => [
                        'contact_id' => $contactId,
                        'is_opt_out' => 1,
                        self::FIELD_OPTOUT_DATE => date('Y-m-d'),
                        self::FIELD_OPTOUT_REASON => $reasons,
                        self::FIELD_OPTOUT_REASON_OTHER => $reasonOther,
                    ],
                ],
            ]));
        })->then(function (Response $response) {
            return $this->prepareResponse($response);
        });
    }

    public function unsubscribe(int $contactId, array $groups) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'GroupContact',
                'action' => 'create',
                'json' => [
                    'status' => 'Removed',
                    'group_id' => $groups,
                    'contact_id' => (string) $contactId,
                ],
            ],
        ]))
            ->then(function (Response $response) {
                return $this->prepareResponse($response);
            });
    }

    /**
     * @param Newsletter[] $newsletters
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
        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'Contact',
                'action' => 'create',
                'json' => [
                        'contact_type' => 'Individual',
                        !is_null($preferencesBefore) ? 'contact_id' : 'email' => $identifier,
                        'first_name' => $firstName ?? '',
                        'last_name' => $lastName ?? '',
                        self::FIELD_PREFERENCES_URL => $preferencesUrl,
                        // Interpret submission as confirmation of desire to receive bulk emails.
                        'is_opt_out' => 0,
                    ] +
                    (
                        $unsubscribeUrl ? [
                            self::FIELD_UNSUBSCRIBE_URL => $unsubscribeUrl,
                        ] : []
                    ) +
                    (
                        $optoutUrl ? [
                            self::FIELD_OPTOUT_URL => $optoutUrl,
                        ] : []
                    ),
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function ($data) {
            return $data['id'];
        })->then(function ($contactId) use ($preferences, $preferencesBefore) {
            $add = array_values(array_diff($preferences, $preferencesBefore ?? []));
            $remove = array_values(array_diff($preferencesBefore ?? [], $preferences));
            $unchanged = array_diff($preferencesBefore ?? [], $add, $remove);

            return Utils::all([
                'added' => !empty($add) ? $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
                    'query' => [
                        'entity' => 'GroupContact',
                        'action' => 'create',
                        'json' => [
                            'status' => 'Added',
                            'group_id' => $this->preferenceGroups($add, empty($preferencesBefore ?? [])),
                            'contact_id' => $contactId,
                        ],
                    ],
                ]))
                    ->then(function (Response $response) {
                        return $this->prepareResponse($response);
                    })
                    ->then(function () use ($add) {
                        return $add;
                    }) : [],
                'removed' => !empty($remove) ? $this->unsubscribe($contactId, $this->preferenceGroups($remove, false))
                    ->then(function () use ($remove) {
                        return $remove;
                    }) : [],
                'unchanged' => $unchanged,
            ])->then(function ($groups) use ($contactId) {
                return [
                    'contact_id' => $contactId,
                    'groups' => $groups,
                ];
            });
        });
    }

    public function checkSubscription(
        string $identifier,
        bool $isEmail = true,
        Newsletter $newsletter = null,
        string $field = null
    ) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest(), $this->options([
            'query' => [
                'entity' => 'Contact',
                'action' => 'get',
                'json' => [
                    (
                        $isEmail ?
                        'email' :
                        ($newsletter ? self::FIELD_UNSUBSCRIBE_URL : ($field ?? self::FIELD_PREFERENCES_URL))
                    ) => $identifier,
                    'return' => [
                        'group',
                        'first_name',
                        'last_name',
                        'email',
                        'is_opt_out',
                        self::FIELD_PREFERENCES_URL,
                    ],
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function ($data) {
            if ($values = $data['values']) {
                $contactId = min(array_keys($values));
                $contact = $values[$contactId];

                return new Subscription(
                    (int) $contact['contact_id'],
                    ('1' === $contact['is_opt_out']),
                    $contact['email'],
                    $contact['first_name'],
                    $contact['last_name'],
                    explode(',', $contact['groups']),
                    $contact[self::FIELD_PREFERENCES_URL]
                );
            }
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

        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'GroupContact',
                'action' => 'create',
                'json' => [
                    'group_id' => [
                        self::GROUP_JOURNAL_ETOC_PREFERENCES,
                    ],
                    'contact_id' => $contactId,
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function () use ($contactId) {
            return [
                'contact_id' => $contactId,
            ];
        });
    }

    public function triggerUnsubscribeEmail(int $contactId) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'GroupContact',
                'action' => 'create',
                'json' => [
                    'group_id' => [
                        self::GROUP_JOURNAL_ETOC_UNSUBSCRIBE,
                    ],
                    'contact_id' => $contactId,
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function () use ($contactId) {
            return [
                'contact_id' => $contactId,
            ];
        });
    }

    private function preferenceGroups(array $preferences, $create = false) : array
    {
        $clean = array_map(function (Newsletter $newsletter) {
            return $newsletter->group();
        }, $preferences);

        if ($create) {
            array_push($clean, self::GROUP_JOURNAL_ETOC_SIGNUP);
        }

        return array_values($clean);
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

    private function prepareRequest(string $method = 'GET', array $headers = []) : Request
    {
        return new Request($method, '', $headers);
    }

    private function options(array $options = []) : array
    {
        $options['query'] = array_map(function ($param) {
            return is_array($param) ? json_encode($param) : $param;
        }, array_merge($options['query'] ?? [], array_filter(['api_key' => $this->apiKey, 'key' => $this->siteKey])));

        return $options;
    }

    /**
     * @param Response $response
     * @return mixed
     * @throws CiviCrmResponseError
     */
    private function prepareResponse(Response $response) : array
    {
        $body = json_decode($response->getBody()->getContents(), true);

        if (!empty($body['is_error'])) {
            throw new CiviCrmResponseError($body['error_message'], $response);
        }

        return $body;
    }
}
