<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * Registers `$table->spamColumns()` and `$table->dropSpamColumns()`.
 *
 * @internal
 */
final class SpamColumns
{
    public const COLUMNS = ['is_spam', 'spam_reason', 'spam_score', 'spam_signals', 'spam_source', 'reviewed_at', 'reviewed_by'];

    public static function register(): void
    {
        Blueprint::macro('spamColumns', function (): void {
            /** @var Blueprint $this */
            $this->boolean('is_spam')->default(false)->index();
            $this->string('spam_reason', 64)->nullable();
            $this->unsignedSmallInteger('spam_score')->default(0);
            $this->json('spam_signals')->nullable();
            $this->string('spam_source', 16)->default('detector');
            $this->timestamp('reviewed_at')->nullable();
            $this->string('reviewed_by')->nullable();
        });

        Blueprint::macro('dropSpamColumns', function (): void {
            /** @var Blueprint $this */
            $this->dropIndex(['is_spam']);
            $this->dropColumn(SpamColumns::COLUMNS);
        });
    }
}
