<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Enums;

use Itxshakil\FormShield\Contracts\Signal;
use Itxshakil\FormShield\Signals\DisposableEmail;
use Itxshakil\FormShield\Signals\DuplicatePayload;
use Itxshakil\FormShield\Signals\ForeignScript;
use Itxshakil\FormShield\Signals\Gibberish;
use Itxshakil\FormShield\Signals\NoJavaScript;
use Itxshakil\FormShield\Signals\NoMxRecord;
use Itxshakil\FormShield\Signals\NoUserAgent;
use Itxshakil\FormShield\Signals\UnexpectedFields;
use Itxshakil\FormShield\Signals\UrlInName;

/**
 * The soft signals that ship with the package. Their values are the keys used
 * in `weights`, in a profile's `signals` list, and in stored signal maps:
 *
 *     'signals' => [BuiltInSignal::NoJavaScript->value, BuiltInSignal::DisposableEmail->value],
 */
enum BuiltInSignal: string
{
    case NoJavaScript = 'no_js';
    case Duplicate = 'duplicate';
    case DisposableEmail = 'disposable_email';
    case ForeignScript = 'foreign_script';
    case NoUserAgent = 'no_user_agent';
    case UrlInName = 'url_in_name';
    case Gibberish = 'gibberish';
    case UnexpectedFields = 'unexpected_fields';
    case NoMxRecord = 'no_mx';

    /** @return class-string<Signal> */
    public function signalClass(): string
    {
        return match ($this) {
            self::NoJavaScript => NoJavaScript::class,
            self::Duplicate => DuplicatePayload::class,
            self::DisposableEmail => DisposableEmail::class,
            self::ForeignScript => ForeignScript::class,
            self::NoUserAgent => NoUserAgent::class,
            self::UrlInName => UrlInName::class,
            self::Gibberish => Gibberish::class,
            self::UnexpectedFields => UnexpectedFields::class,
            self::NoMxRecord => NoMxRecord::class,
        };
    }
}
