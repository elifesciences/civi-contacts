<?php

namespace eLife\CiviContacts\Guzzle;

use eLife\CiviContacts\Model\Subscriber;
use GuzzleHttp\Promise\PromiseInterface;

interface CiviCrmClientInterface
{
    public function storeSubscriberUrls(Subscriber $subscriber) : PromiseInterface;

    public function getAllSubscribers(int $ceiling = 0, int $limit = 100, int $offset = 0) : array;
}
