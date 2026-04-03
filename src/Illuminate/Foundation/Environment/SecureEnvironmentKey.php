<?php

namespace Illuminate\Foundation\Environment;

class SecureEnvironmentKey
{
    /**
     * Create a new secure environment key instance.
     *
     * @param  string  $value
     * @param  string  $source
     * @param  \Illuminate\Foundation\Environment\SecureEnvironmentKeyProvider  $provider
     */
    public function __construct(
        public string $value,
        public string $source,
        public SecureEnvironmentKeyProvider $provider,
    ) {
    }
}
