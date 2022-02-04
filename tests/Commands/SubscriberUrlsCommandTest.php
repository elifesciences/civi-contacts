<?php

namespace tests\eLife\CiviContacts\Commands;

use eLife\CiviContacts\Guzzle\CiviCrmClientInterface;
use eLife\CiviContacts\Model\Subscriber;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use tests\eLife\CiviContacts\TestCase;

final class SubscriberUrlsCommandTest extends TestCase
{
    /**
     * @test
     */
    public function it_will_run_the_subscribers_urls_command()
    {
        $this->artisan('subscriber:urls', [
            '--total' => 100,
            '--batch-size' => 20,
            '--offset' => 3,
        ])
            ->expectsOutput('Finding civi contacts without preference and unsubscribe urls.')
            ->expectsOutput('4 subscribers found.')
            ->expectsOutput('Updating contacts with preference urls and unsubscribe urls.')
            ->expectsOutput('Updating contact 1. (1 of 4)')
            ->expectsOutput('Updating contact 2. (2 of 4)')
            ->expectsOutput('Updating contact 3. (3 of 4)')
            ->expectsOutput('Updating contact 123. (4 of 4)')
            ->assertExitCode(0);
    }
}
