<?php

namespace tests\eLife\CiviContacts\Commands;

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
            ->expectsOutput('Finding civi contacts without preference, unsubscribe and opt-out urls.')
            ->expectsOutput('5 subscribers found.')
            ->expectsOutput('Updating contacts with preference, unsubscribe and opt-out urls.')
            ->expectsOutput('Updating contact 1. (1 of 5)')
            ->expectsOutput('Updating contact 2. (2 of 5)')
            ->expectsOutput('Updating contact 3. (3 of 5)')
            ->expectsOutput('Updating contact 4. (4 of 5)')
            ->expectsOutput('Updating contact 123. (5 of 5)')
            ->assertExitCode(0);
    }
}
