<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Itxshakil\FormShield\Tests\TestCase;
use Itxshakil\FormShield\Token;
use PHPUnit\Framework\Attributes\Test;

final class ComponentTest extends TestCase
{
    #[Test]
    public function it_renders_an_accessible_honeypot_and_a_valid_token(): void
    {
        $html = Blade::render('<form><x-form-shield /></form>');

        self::assertStringContainsString('name="fax_number"', $html);
        self::assertStringContainsString('tabindex="-1"', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
        self::assertStringContainsString('autocomplete="off"', $html);
        self::assertStringContainsString('left:-9999px', $html);
        self::assertStringContainsString('data-form-shield-js', $html);

        preg_match('/name="_fs_started" value="([^"]+)"/', $html, $m);
        self::assertNotEmpty($m[1] ?? null);
        self::assertSame(now()->getTimestamp(), app(Token::class)->read(html_entity_decode($m[1])));
    }

    #[Test]
    public function the_label_points_at_the_honeypot_and_ids_are_unique_per_render(): void
    {
        $html = Blade::render('<x-form-shield /><x-form-shield />');

        preg_match_all('/id="(fax_number-[a-z0-9]+)"/', $html, $ids);
        self::assertCount(2, array_unique($ids[1]));

        foreach ($ids[1] as $id) {
            self::assertStringContainsString('for="'.$id.'"', $html);
        }
    }

    #[Test]
    public function a_honeypot_class_replaces_the_inline_style(): void
    {
        config()->set('form-shield.honeypot_class', 'sr-trap');

        $html = Blade::render('<x-form-shield />');

        self::assertStringContainsString('class="sr-trap"', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    #[Test]
    public function wire_mode_binds_livewire_models_instead_of_values(): void
    {
        $html = Blade::render('<x-form-shield wire />');

        self::assertStringContainsString('wire:model="formShield._fs_started"', $html);
        self::assertStringContainsString('wire:model="formShield._fs_js"', $html);
        self::assertStringContainsString('wire:model="formShield.fax_number"', $html);
        self::assertDoesNotMatchRegularExpression('/name="_fs_started" value=/', $html);
    }

    #[Test]
    public function the_script_directive_inlines_the_script_with_the_csp_nonce(): void
    {
        Vite::useCspNonce('abc123');

        $html = Blade::render('@formShieldScripts');

        self::assertStringContainsString('<script nonce="abc123">', $html);
        self::assertStringContainsString('data-form-shield-token', $html);
    }

    #[Test]
    public function the_script_directive_omits_the_nonce_when_none_is_set(): void
    {
        $html = Blade::render('@formShieldScripts');

        self::assertStringStartsWith('<script>', trim($html));
    }
}
