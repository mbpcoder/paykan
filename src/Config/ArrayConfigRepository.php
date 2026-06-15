<?php

namespace MbpCoder\Payment\Config;

/**
 * A framework-agnostic configuration repository backed by a plain array.
 *
 * Supports "dot" notation access, e.g. get('channels.ipg.default').
 */
class ArrayConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(private array $items = [])
    {
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $array = &$this->items;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $array[$segment] = $value;
                break;
            }
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }
}
