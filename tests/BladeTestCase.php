<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests;

/**
 * Boots the package with ui_driver=blade so the Blade/Livewire and settings routes are registered.
 */
abstract class BladeTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('accounting.ui_driver', 'blade');
    }
}
