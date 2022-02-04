<?php

namespace eLife\CiviContacts\Model;

final class Subscriber
{
    const PREFERENCES_URL_STEM = '/content-alerts/';
    const UNSUBSCRIBE_URL_STEM = '/content-alerts/unsubscribe/';

    private $id;
    private $preferencesUrl;
    private $unsubscribeUrl;
    private $preparePreferencesUrl = null;
    private $prepareUnsubscribeUrl = null;

    public function __construct(int $id, string $preferencesUrl = null, string $unsubscribeUrl = null)
    {
        $this->id = $id;
        $this->preferencesUrl = $preferencesUrl;
        $this->unsubscribeUrl = $unsubscribeUrl;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getPreferencesUrl() : ?string
    {
        return $this->preparePreferencesUrl ?? $this->trimUrl($this->preferencesUrl);
    }

    public function getUnsubscribeUrl() : ?string
    {
        return $this->prepareUnsubscribeUrl ?? $this->trimUrl($this->unsubscribeUrl);
    }

    public function prepareUrls() : void
    {
        $this->preparePreferencesUrl = $this->prepareUrl($this->getPreferencesUrl());
        $this->prepareUnsubscribeUrl = $this->prepareUrl($this->getUnsubscribeUrl(), self::UNSUBSCRIBE_URL_STEM);
    }

    private function prepareUrl(string $url = null, $stem = self::PREFERENCES_URL_STEM) : string
    {
        if (is_null($url)) {
            return env('JOURNAL_URI', 'https://elifesciences.org').$stem.uniqid();
        }

        return trim($url);
    }

    private function trimUrl(string $url = null) : ?string
    {
        return (empty($url ?? trim($url))) ? null : trim($url);
    }
}
