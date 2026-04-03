<?php

namespace Illuminate\Foundation\Environment;

use Illuminate\Support\Env;

class EnvironmentVariableSecureEnvironmentKeyProvider implements SecureEnvironmentKeyProvider
{
    /**
     * Create a new environment variable key provider instance.
     *
     * @param  string  $environmentVariable
     */
    public function __construct(protected string $environmentVariable = 'LARAVEL_ENV_SECURE_KEY')
    {
    }

    /**
     * Get the provider display name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Environment variable ['.$this->environmentVariable.']';
    }

    /**
     * Retrieve the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @return string|null
     */
    public function get(string $appPath): ?string
    {
        return Env::get($this->environmentVariable);
    }
}
