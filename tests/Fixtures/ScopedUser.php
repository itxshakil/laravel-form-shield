<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** A users model whose global scope hides every row, like a tenant scope with no tenant. */
final class ScopedUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $builder->whereRaw('1 = 0');
            }
        });
    }
}
