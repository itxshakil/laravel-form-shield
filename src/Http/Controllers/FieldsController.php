<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Itxshakil\FormShield\FormShield;

/**
 * Fresh shield fields as JSON, for forms rendered by JavaScript or embedded on
 * another origin. Enable it with `form-shield.route.enabled`.
 */
final class FieldsController
{
    public function __invoke(FormShield $shield): JsonResponse
    {
        return new JsonResponse([
            'names' => $shield->fieldNames(),
            'fields' => $shield->fields(),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }
}
