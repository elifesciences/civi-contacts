<?php

namespace eLife\CiviContacts\Commands;

use eLife\CiviContacts\Guzzle\CiviCrmClientInterface;
use eLife\CiviContacts\Model\Subscriber;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

final class SubscriberUrlsCommand extends Command
{
    /**
     * @var CiviCrmClientInterface
     */
    private $civiCrmClient;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'subscriber:urls';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Update subscribers with preference and unsubscribe urls.';

    public function __construct(CiviCrmClientInterface $civiCrmClient)
    {
        parent::__construct();

        $this->civiCrmClient = $civiCrmClient;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Finding civi contacts without preference and unsubscribe urls.');

        $subscribers = $this->civiCrmClient->getAllSubscribers(
            env('CIVI_QUERY_CEILING', 500),
            env('CIVI_QUERY_LIMIT', 100)
        );

        $this->info(count($subscribers).' subscribers found.');
        $this->info('Updating contacts with preference urls and unsubscribe urls.');

        /** @var Subscriber $subscriber */
        foreach ($subscribers as $subscriber) {
            $subscriber->prepareUrls();
            /** @var Subscriber $store */
            $store = $this->civiCrmClient->storeSubscriberUrls($subscriber)->wait();
            $this->info('Updating contact '.$store->getId().'.');
        }
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
