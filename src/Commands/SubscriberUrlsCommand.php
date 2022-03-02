<?php

namespace eLife\CiviContacts\Commands;

use eLife\CiviContacts\Etoc\Subscription;
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
    protected $signature = 'subscriber:urls {--total= : Total (default: 500, set to 0 to retrieve all subscribers)}
                    {--batch-size= : Handle queries in batches of (max: 100, default: 100)}
                    {--offset= : Query offset (default: 0)}';

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
        $this->info('Finding civi contacts without preference, unsubscribe and opt-out urls.');

        $subscribers = $this->civiCrmClient->getAllSubscribers(
            $this->option('total') ?? 500,
            $this->option('batch-size') ?? 100,
            $this->option('offset') ?? 0
        );

        $this->info(count($subscribers).' subscribers found.');
        $this->info('Updating contacts with preference, unsubscribe and opt-out urls.');

        $co = 0;
        $total = count($subscribers);
        /** @var Subscription $subscriber */
        foreach ($subscribers as $subscriber) {
            $co++;
            $subscriber->prepareUrls();
            /** @var Subscription $store */
            $store = $this->civiCrmClient->storeSubscriberUrls($subscriber)->wait();
            $this->info('Updating contact '.$store->getId().'. ('.$co.' of '.$total.')');
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
