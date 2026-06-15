<?php

namespace MbpCoder\Payment\Support;

/**
 * Minimal, framework-agnostic string helpers used by the package so it does
 * not depend on illuminate/support.
 */
final class Str
{
    /**
     * Determine if the given haystack contains any of the given needles.
     *
     * @param string|string[] $needles
     */
    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert a "snake_case" or "kebab-case" or "space cased" string to camelCase.
     */
    public static function camel(string $value): string
    {
        $studly = str_replace(['-', '_'], ' ', $value);
        $studly = str_replace(' ', '', ucwords($studly));
        return lcfirst($studly);
    }
}
