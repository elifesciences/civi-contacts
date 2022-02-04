<?php

namespace eLife\CiviContacts\Guzzle;

use eLife\CiviContacts\Exception\CiviCrmResponseError;
use eLife\CiviContacts\Model\Subscriber;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class CiviCrmClient implements CiviCrmClientInterface
{
    const GROUP_LATEST_ARTICLES = 'All_Content_53';
    const GROUP_EARLY_CAREER = 'early_careers_news_317';
    const GROUP_TECHNOLOGY = 'technology_news_435';
    const GROUP_ELIFE_NEWSLETTER = 'eLife_bi_monthly_news_1032';
    // Custom field to store user preferences link to be included in emails.
    const FIELD_PREFERENCES_URL = 'custom_131';
    // Custom field to store user unsubscribe link to be included in emails.
    const FIELD_UNSUBSCRIBE_URL = 'custom_132';

    private $client;
    private $apiKey;
    private $siteKey;

    public function __construct(ClientInterface $client, string $apiKey, string $siteKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->siteKey = $siteKey;
    }

    public function storeSubscriberUrls(Subscriber $subscriber) : PromiseInterface
    {
        return $this->client->sendAsync($this->prepareRequest('POST'), $this->options([
            'query' => [
                'entity' => 'Contact',
                'action' => 'create',
                'json' => [
                    'contact_id' => $subscriber->getId(),
                    self::FIELD_PREFERENCES_URL => $subscriber->getPreferencesUrl(),
                    self::FIELD_UNSUBSCRIBE_URL => $subscriber->getUnsubscribeUrl(),
                ],
            ],
        ]))->then(function (Response $response) {
            return $this->prepareResponse($response);
        })->then(function (array $data) use ($subscriber) {
            return new Subscriber(
                (int) $data['id'],
                $subscriber->getPreferencesUrl(),
                $subscriber->getUnsubscribeUrl()
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
                    ],
                    'group' => [
                        self::GROUP_LATEST_ARTICLES,
                        self::GROUP_EARLY_CAREER,
                        self::GROUP_TECHNOLOGY,
                        self::GROUP_ELIFE_NEWSLETTER,
                    ],
                    self::FIELD_UNSUBSCRIBE_URL => ['IS NULL' => 1],
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
                return new Subscriber(
                    (int) $contact['id'],
                    $contact[self::FIELD_PREFERENCES_URL],
                    $contact[self::FIELD_UNSUBSCRIBE_URL]
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
