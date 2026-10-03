<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Enums;

/** Where form-shield:install stores verdicts. */
enum StorageOption: string
{
    case Columns = 'columns';
    case Log = 'log';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::Columns => 'Add spam columns to a table I already have (e.g. inquiries, leads)',
            self::Log => "Use Form Shield's own submissions table (my form doesn't store posts)",
            self::Skip => 'Skip for now',
        };
    }

    public static function fromLabel(string $label): self
    {
        foreach (self::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        return self::Skip;
    }

    /** @return list<string> */
    public static function labels(): array
    {
        return array_map(static fn (self $case): string => $case->label(), self::cases());
    }
}
