<?php

declare(strict_types=1);

namespace Bladeberg\Tests;

use Bladeberg\BladebergServiceProvider;
use Bladeberg\Facades\Bladeberg;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BladebergServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Bladeberg' => Bladeberg::class];
    }
}
