<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ApiPagination
{
    public static function perPage(Request $request, ?int $default = null, ?int $max = null): int
    {
        $default ??= (int) config('eralms.performance.default_per_page', 25);
        $max ??= (int) config('eralms.performance.max_per_page', 100);

        return max(1, min($max, $request->integer('per_page', $default)));
    }
}
