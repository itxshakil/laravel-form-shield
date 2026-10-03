<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Itxshakil\FormShield\Facades\FormShield;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Token;
use PHPUnit\Framework\Attributes\Test;

final class FieldsEndpointTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('form-shield.route', [
            'enabled' => true,
            'uri' => 'form-shield/fields',
            'name' => 'form-shield.fields',
            'middleware' => [],
        ]);
    }

    #[Test]
    public function it_returns_fresh_fields_and_their_names(): void
    {
        $response = $this->getJson(route('form-shield.fields'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('names.honeypot', 'fax_number')
            ->assertJsonPath('names.timestamp', '_fs_started')
            ->assertJsonPath('names.js', '_fs_js')
            ->assertJsonPath('fields.fax_number', '')
            ->assertJsonPath('fields._fs_js', '');

        self::assertSame(now()->getTimestamp(), app(Token::class)->read((string) $response->json('fields._fs_started')));
    }

    #[Test]
    public function fields_keep_honeypot_timestamp_js_order(): void
    {
        self::assertSame(['fax_number', '_fs_started', '_fs_js'], array_keys(FormShield::fields()));
    }
}
