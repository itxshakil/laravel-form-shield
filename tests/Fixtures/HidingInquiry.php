<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Itxshakil\FormShield\Concerns\HasSpamVerdict;

/** An inquiries model that hides its quarantined rows behind a global scope, as an app might. */
final class HidingInquiry extends Model
{
    use HasSpamVerdict;

    protected $table = 'inquiries';

    protected static function booted(): void
    {
        self::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $builder->where($model->qualifyColumn('is_spam'), false);
            }
        });
    }
}
