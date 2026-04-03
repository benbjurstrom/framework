<?php

namespace Illuminate\Foundation\Environment;

interface SecureEnvironmentKeyProvider
{
    /**
     * Get the provider display name.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Retrieve the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @return string|null
     */
    public function get(string $appPath): ?string;
}
