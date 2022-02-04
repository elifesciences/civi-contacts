<?php

namespace tests\eLife\CiviContacts;

use Illuminate\Contracts\Console\Kernel;
use tests\eLife\CiviContacts\Fakes\FakeServiceProvider;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->register(FakeServiceProvider::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
