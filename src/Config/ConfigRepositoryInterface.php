<?php

namespace MbpCoder\Payment\Config;

interface ConfigRepositoryInterface
{
    /**
     * Get a configuration value using "dot" notation.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;
}
