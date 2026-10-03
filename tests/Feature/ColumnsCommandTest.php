<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Itxshakil\FormShield\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ColumnsCommandTest extends TestCase
{
    private string $migrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrations = sys_get_temp_dir().'/form-shield-'.bin2hex(random_bytes(4));
        $this->app->useDatabasePath($this->migrations);

        Schema::create('leads', static function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->migrations);

        parent::tearDown();
    }

    /** @return list<string> */
    private function written(): array
    {
        return glob($this->migrations.'/migrations/*.php') ?: [];
    }

    #[Test]
    public function it_writes_a_migration_that_adds_the_columns(): void
    {
        $this->artisan('form-shield:columns', ['table' => 'leads'])
            ->expectsOutputToContain('add_spam_columns_to_leads_table.php]')
            ->expectsConfirmation('Run migrations now?', 'no')
            ->expectsOutputToContain('App\\Models\\Lead')
            ->expectsOutputToContain('Lead::createWithVerdict($data, $verdict);')
            ->assertSuccessful();

        Artisan::call('form-shield:columns', ['table' => 'leads', '--no-interaction' => true]);
        self::assertStringNotContainsString('Inquiry', Artisan::output());

        $files = $this->written();
        self::assertCount(1, $files);
        self::assertMatchesRegularExpression('/\d{4}_\d{2}_\d{2}_\d{6}_add_spam_columns_to_leads_table\.php$/', $files[0]);

        $migration = require $files[0];
        self::assertInstanceOf(Migration::class, $migration);

        $migration->up();
        self::assertTrue(Schema::hasColumns('leads', ['is_spam', 'spam_reason', 'spam_score', 'spam_signals', 'spam_source', 'reviewed_at', 'reviewed_by']));

        $migration->down();
        self::assertFalse(Schema::hasColumn('leads', 'is_spam'));
        self::assertTrue(Schema::hasColumn('leads', 'email'));
    }

    #[Test]
    public function it_does_not_write_a_second_migration_for_the_same_table(): void
    {
        $this->artisan('form-shield:columns', ['table' => 'leads', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('form-shield:columns', ['table' => 'leads', '--no-interaction' => true])
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();

        self::assertCount(1, $this->written());
    }

    #[Test]
    public function it_refuses_a_table_that_already_has_the_columns(): void
    {
        Schema::table('leads', static fn (Blueprint $table) => $table->spamColumns());

        $this->artisan('form-shield:columns', ['table' => 'leads'])
            ->expectsOutputToContain('already has')
            ->assertFailed();

        self::assertSame([], $this->written());
    }

    #[Test]
    public function it_warns_but_writes_for_a_table_that_does_not_exist_yet(): void
    {
        $this->artisan('form-shield:columns', ['table' => 'future_leads'])
            ->expectsOutputToContain('does not exist yet, so the migration was not run')
            ->assertSuccessful();

        self::assertCount(1, $this->written());
    }

    #[Test]
    public function migrate_is_skipped_when_the_table_does_not_exist_yet(): void
    {
        // Running it would fail now, and break every later `migrate` too.
        $this->artisan('form-shield:columns', ['table' => 'future_leads', '--migrate' => true])
            ->expectsOutputToContain('was not run')
            ->assertSuccessful();

        self::assertFalse(Schema::hasTable('future_leads'));
        self::assertCount(1, $this->written());
    }

    #[Test]
    public function migrate_runs_the_migration_when_the_table_exists(): void
    {
        $this->artisan('form-shield:columns', ['table' => 'leads', '--migrate' => true])->assertSuccessful();

        self::assertTrue(Schema::hasColumn('leads', 'is_spam'));
    }

    #[Test]
    public function answering_yes_runs_the_migration(): void
    {
        $this->artisan('form-shield:columns', ['table' => 'leads'])
            ->expectsConfirmation('Run migrations now?', 'yes')
            ->assertSuccessful();

        self::assertTrue(Schema::hasColumn('leads', 'is_spam'));
    }

    #[Test]
    public function it_rejects_unsafe_table_names(): void
    {
        self::assertSame(1, Artisan::call('form-shield:columns', ['table' => 'leads; drop table users']));
        self::assertStringContainsString('not a valid table name', Artisan::output());
        self::assertStringNotContainsString('does not exist yet', Artisan::output());

        self::assertSame([], $this->written());
    }
}
