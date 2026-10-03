<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests;

use Illuminate\Support\Carbon;
use Itxshakil\FormShield\Contracts\MxResolver;
use Itxshakil\FormShield\FormShieldServiceProvider;
use Itxshakil\FormShield\Testing\InteractsWithFormShield;
use Itxshakil\FormShield\Tests\Fixtures\FakeMxResolver;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use InteractsWithFormShield;

    protected FakeMxResolver $mx;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 12:00:00');

        $this->mx = new FakeMxResolver;
        $this->instance(MxResolver::class, $this->mx);

        // The known-sender exemption queries a users table; only DB-backed tests turn it on.
        config()->set('form-shield.exemption.model', Fixtures\User::class);
        config()->set('form-shield.exemption.enabled', false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app)
    {
        return [FormShieldServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
    }

    /**
     * A human-looking submission: valid token, JS armed, real-looking content.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function humanPayload(array $overrides = [], int $secondsAgo = 30): array
    {
        return array_replace([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello, I would like a quote for hosting my small business website.',
        ], $this->formShieldFields($secondsAgo), $overrides);
    }
}
