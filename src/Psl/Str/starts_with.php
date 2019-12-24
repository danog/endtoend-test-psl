<?php

declare(strict_types=1);

namespace Psl\Str;

/**
 * Returns whether the string starts with the given prefix.
 */
function starts_with(string $str, string $prefix): bool
{
    return 0 === search($str, $prefix);
}