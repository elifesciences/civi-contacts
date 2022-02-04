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
        $this->artisan('subscriber:urls')
            ->expectsOutput('Finding civi contacts without preference and unsubscribe urls.')
            ->expectsOutput('3 subscribers found.')
            ->expectsOutput('Updating contacts with preference urls and unsubscribe urls.')
            ->expectsOutput('Updating contact 1.')
            ->expectsOutput('Updating contact 2.')
            ->expectsOutput('Updating contact 3.')
            ->assertExitCode(0);
    }
}
