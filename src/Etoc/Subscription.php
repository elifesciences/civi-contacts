<?php

namespace eLife\CiviContacts\Etoc;

final class Subscription
{
    const PREFERENCES_URL_STEM = '/content-alerts/';
    const UNSUBSCRIBE_URL_STEM = '/content-alerts/unsubscribe/';
    const OPTOUT_URL_STEM = '/content-alerts/optout/';

    private $id;
    private $preferencesUrl;
    private $unsubscribeUrl;
    private $optoutUrl;
    private $preparePreferencesUrl = null;
    private $prepareUnsubscribeUrl = null;
    private $prepareOptoutUrl = null;
    private $optOut;
    private $email;
    private $firstName;
    private $lastName;
    private $preferences;

    public function __construct(
        int $id,
        bool $optOut = null,
        string $email = null,
        string $firstName = null,
        string $lastName = null,
        array $preferences = [],
        string $preferencesUrl = null,
        string $unsubscribeUrl = null,
        string $optoutUrl = null
    )
    {
        $this->id = $id;
        $this->optOut = $optOut;
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->setPreferences($preferences);
        $this->preferencesUrl = $preferencesUrl;
        $this->unsubscribeUrl = $unsubscribeUrl;
        $this->optoutUrl = $optoutUrl;
    }

    public static function urlsOnly(
        int $id,
        string $preferencesUrl = null,
        string $unsubscribeUrl = null,
        string $optoutUrl = null
    ) : Subscription
    {
        return new static(
            $id,
            null,
            null,
            null,
            null,
            [],
            $preferencesUrl,
            $unsubscribeUrl,
            $optoutUrl
        );
    }

    public static function getNewsletters(array $preferences) : array
    {
        $groups = [
            LatestArticles::LABEL => new LatestArticles(),
            EarlyCareer::LABEL => new EarlyCareer(),
            Technology::LABEL => new Technology(),
            ElifeNewsletter::LABEL => new ElifeNewsletter(),
        ];

        return array_map(function ($preference) use ($groups) {
            return $groups[$preference];
        }, array_intersect(array_keys($groups), $preferences));
    }

    private function setPreferences(array $preferences)
    {
        $groups = [
            LatestArticles::GROUP_ID => new LatestArticles(),
            EarlyCareer::GROUP_ID => new EarlyCareer(),
            Technology::GROUP_ID => new Technology(),
            ElifeNewsletter::GROUP_ID => new ElifeNewsletter(),
        ];

        $this->preferences = array_values(array_map(function ($preference) use ($groups) {
            return $groups[$preference];
        }, array_intersect(array_keys($groups), $preferences)));
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getOptOut() : ?bool
    {
        return $this->optOut ?? null;
    }

    public function getEmail() : ?string
    {
        return $this->email ?? null;
    }

    public function getFirstName() : ?string
    {
        return $this->firstName ?? null;
    }

    public function getLastName() : ?string
    {
        return $this->lastName ?? null;
    }

    /**
     * @return Newsletter[]
     */
    public function getPreferences()
    {
        return $this->preferences;
    }

    public function getPreferencesUrl() : ?string
    {
        return $this->preparePreferencesUrl ?? $this->trimUrl($this->preferencesUrl);
    }

    public function getUnsubscribeUrl() : ?string
    {
        return $this->prepareUnsubscribeUrl ?? $this->trimUrl($this->unsubscribeUrl);
    }

    public function getOptoutUrl() : ?string
    {
        return $this->prepareOptoutUrl ?? $this->trimUrl($this->optoutUrl);
    }

    public function prepareUrls() : void
    {
        $this->preparePreferencesUrl = $this->prepareUrl($this->getPreferencesUrl());
        $this->prepareUnsubscribeUrl = $this->prepareUrl($this->getUnsubscribeUrl(), self::UNSUBSCRIBE_URL_STEM);
        $this->prepareOptoutUrl = $this->prepareUrl($this->getOptoutUrl(), self::OPTOUT_URL_STEM);
    }

    public function data() : array
    {
        $preferences = array_map(function (Newsletter $preference) {
            return $preference->label();
        }, $this->getPreferences());

        return [
            'contact_id' => $this->getId(),
            'email' => $this->getEmail(),
            'preferences' => $preferences,
            'groups' => implode(',', $preferences),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
        ];
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
