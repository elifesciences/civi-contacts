<?php

namespace test\eLife\CiviContacts\Etoc;

use eLife\CiviContacts\Etoc\EarlyCareer;
use eLife\CiviContacts\Etoc\LatestArticles;
use eLife\CiviContacts\Etoc\Newsletter;
use eLife\CiviContacts\Etoc\Subscription;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_stores_a_subscription_profile()
    {
        $subscription = new Subscription(1, false, 'example@email.com', 'First', 'Last', [LatestArticles::GROUP_ID]);

        $this->assertSame(1, $subscription->getId());
        $this->assertFalse($subscription->getOptOut());
        $this->assertSame('example@email.com', $subscription->getEmail());
        $this->assertSame('First', $subscription->getFirstName());
        $this->assertSame('Last', $subscription->getLastName());
        $this->assertEquals([new LatestArticles()], $subscription->getPreferences());
    }

    /**
     * @test
     */
    public function it_may_have_a_preferences_url()
    {
        $with = new Subscription(1, false, 'example@email.com', '', '', [], 'http://localhost/content-alerts/foo');
        $withOut = new Subscription(1, false, 'example@email.com', '', '', []);

        $this->assertSame('http://localhost/content-alerts/foo', $with->getPreferencesUrl());
        $this->assertNull($withOut->getPreferencesUrl());
    }

    /**
     * @test
     */
    public function it_can_prepare_data_array_for_form()
    {
        $subscription = new Subscription(
            1,
            false,
            'example@email.com',
            'First',
            'Last',
            [
                LatestArticles::GROUP_ID,
                EarlyCareer::GROUP_ID,
            ]
        );

        $this->assertSame([
            'contact_id' => 1,
            'email' => 'example@email.com',
            'preferences' => [
                'latest_articles',
                'early_career',
            ],
            'groups' => 'latest_articles,early_career',
            'first_name' => 'First',
            'last_name' => 'Last',
        ], $subscription->data());
    }

    /**
     * @test
     */
    public function it_will_prepare_only_recognised_newsletter_preferences()
    {
        $unknown1 = 1;
        $unknown2 = 999;

        $subscription = new Subscription(1, false, '', '', '', [$unknown1, $unknown2]);

        $this->assertCount(0, $subscription->getPreferences());

        $subscription = new Subscription(
            1,
            false,
            '',
            '',
            '',
            [
                $unknown1,
                LatestArticles::GROUP_ID,
                $unknown2,
                EarlyCareer::GROUP_ID,
            ]
        );

        $this->assertCount(2, $subscription->getPreferences());
        $this->assertInstanceOf(Newsletter::class, $subscription->getPreferences()[0]);
        $this->assertInstanceOf(Newsletter::class, $subscription->getPreferences()[1]);
    }
}
