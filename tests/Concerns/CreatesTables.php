<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesTables
{
    protected function createTables(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('inquiries', static function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->spamColumns();
            $table->timestamps();
        });
    }
}
