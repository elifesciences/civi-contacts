<?php

namespace test\eLife\CiviContacts\Etoc;

use eLife\CiviContacts\Etoc\EarlyCareer;
use eLife\CiviContacts\Etoc\ElifeNewsletter;
use eLife\CiviContacts\Etoc\LatestArticles;
use eLife\CiviContacts\Etoc\Newsletter;
use eLife\CiviContacts\Etoc\Subscription;
use PHPUnit\Framework\TestCase;
use Traversable;

final class SubscriptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_stores_a_subscription_profile()
    {
        $subscription = new Subscription(1, false, 'example@email.com', 'First', 'Last', [LatestArticles::GROUP_ID]);

        $this->assertSame(1, $subscription->getId());
        $this->assertFalse($subscription->getOptout());
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
    public function it_may_have_an_unsubscribe_url()
    {
        $with = Subscription::urlsOnly(1, null, 'http://localhost/content-alerts/unsubscribe/foo');
        $withOut = Subscription::urlsOnly(1);

        $this->assertSame('http://localhost/content-alerts/unsubscribe/foo', $with->getUnsubscribeUrl());
        $this->assertNull($withOut->getUnsubscribeUrl());
    }

    /**
     * @test
     */
    public function it_may_have_an_optout_url()
    {
        $with = Subscription::urlsOnly(1, null, null, 'http://localhost/content-alerts/optout/foo');
        $withOut = Subscription::urlsOnly(1);

        $this->assertSame('http://localhost/content-alerts/optout/foo', $with->getOptoutUrl());
        $this->assertNull($withOut->getUnsubscribeUrl());
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

    /**
     * @test
     * @dataProvider preferencesProvider
     */
    public function it_will_prepare_newsletters_from_preferences(array $preferences, array $expectedNewsletters)
    {
        $this->assertEquals($expectedNewsletters, Subscription::getNewsletters($preferences));
    }

    public function preferencesProvider() : Traversable
    {
        yield 'empty' => [
            [],
            [],
        ];
        yield 'single' => [
            [
                'latest_articles',
            ],
            [
                new LatestArticles(),
            ],
        ];
        yield 'multiple' => [
            [
                'early_career',
                'latest_articles',
            ],
            [
                new LatestArticles(),
                new EarlyCareer(),
            ],
        ];
        yield 'unrecognised' => [
            [
                'unrecognised',
            ],
            [],
        ];
        yield 'recognised and unrecognised' => [
            [
                'unrecognised',
                'latest_articles',
                'elife_newsletter',
            ],
            [
                new LatestArticles(),
                new ElifeNewsletter(),
            ],
        ];
        yield 'repeated' => [
            [
                'latest_articles',
                'elife_newsletter',
                'latest_articles',
            ],
            [
                new LatestArticles(),
                new ElifeNewsletter(),
            ],
        ];
        yield 'all' => [
            [
                'latest_articles',
                'early_career',
                'elife_newsletter',
            ],
            [
                new LatestArticles(),
                new EarlyCareer(),
                new ElifeNewsletter(),
            ],
        ];
    }

    /**
     * @test
     */
    public function urls_can_be_set_or_prepared()
    {
        $notSet = Subscription::urlsOnly(1);

        $this->assertNull($notSet->getPreferencesUrl());
        $this->assertNull($notSet->getUnsubscribeUrl());
        $this->assertNull($notSet->getOptoutUrl());

        $set = Subscription::urlsOnly(
            1,
            'http://localhost/content-alerts/foo',
            'http://localhost/content-alerts/unsubscribe/foo',
            'http://localhost/content-alerts/optout/foo'
        );

        $this->assertEquals('http://localhost/content-alerts/foo', $set->getPreferencesUrl());
        $this->assertEquals('http://localhost/content-alerts/unsubscribe/foo', $set->getUnsubscribeUrl());
        $this->assertEquals('http://localhost/content-alerts/optout/foo', $set->getOptoutUrl());

        $notSet->prepareUrls();

        $this->assertStringStartsWith('https://elifesciences.org/content-alerts/', $notSet->getPreferencesUrl());
        $this->assertStringStartsWith('https://elifesciences.org/content-alerts/unsubscribe/', $notSet->getUnsubscribeUrl());
        $this->assertStringStartsWith('https://elifesciences.org/content-alerts/optout/', $notSet->getOptoutUrl());
    }
}
