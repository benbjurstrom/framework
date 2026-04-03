<?php

namespace Illuminate\Foundation\Environment;

interface WritesSecureEnvironmentKeys extends SecureEnvironmentKeyProvider
{
    /**
     * Persist the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @param  string  $key
     * @return void
     */
    public function store(string $appPath, string $key): void;

    /**
     * Delete the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @return void
     */
    public function delete(string $appPath): void;
}
