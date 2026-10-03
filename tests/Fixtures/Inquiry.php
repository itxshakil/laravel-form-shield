<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Itxshakil\FormShield\Concerns\HasSpamVerdict;

final class Inquiry extends Model
{
    use HasSpamVerdict;

    protected $table = 'inquiries';

    /**
     * A realistic $fillable list that does NOT include the spam columns, so
     * the tests exercise the mass-assignment case real apps hit.
     *
     * @var list<string>
     */
    protected $fillable = ['email', 'message'];
}
