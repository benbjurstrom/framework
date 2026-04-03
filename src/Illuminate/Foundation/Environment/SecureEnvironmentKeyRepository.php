<?php

namespace Illuminate\Foundation\Environment;

use RuntimeException;

class SecureEnvironmentKeyRepository
{
    /**
     * Create a new secure environment key repository instance.
     *
     * @param  array<int, \Illuminate\Foundation\Environment\SecureEnvironmentKeyProvider>  $providers
     */
    public function __construct(protected array $providers)
    {
    }

    /**
     * Create the default secure environment key repository.
     *
     * @return static
     */
    public static function createDefault()
    {
        return new static([
            new MacOsKeychainSecureEnvironmentKeyProvider,
            new EnvironmentVariableSecureEnvironmentKeyProvider,
        ]);
    }

    /**
     * Retrieve the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @return string|null
     */
    public function get(string $appPath): ?string
    {
        return $this->find($appPath)?->value;
    }

    /**
     * Retrieve the secure environment key and its source for the given application path.
     *
     * @param  string  $appPath
     * @return \Illuminate\Foundation\Environment\SecureEnvironmentKey|null
     */
    public function find(string $appPath): ?SecureEnvironmentKey
    {
        foreach ($this->providers as $provider) {
            if (! is_null($key = $provider->get($appPath))) {
                return new SecureEnvironmentKey($key, $provider->name(), $provider);
            }
        }

        return null;
    }

    /**
     * Persist the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @param  string  $key
     * @return void
     */
    public function store(string $appPath, string $key): void
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof WritesSecureEnvironmentKeys) {
                $provider->store($appPath, $key);

                return;
            }
        }

        throw new RuntimeException('No writable secure environment key provider is available.');
    }

    /**
     * Delete the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @return void
     */
    public function delete(string $appPath): void
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof WritesSecureEnvironmentKeys) {
                $provider->delete($appPath);

                return;
            }
        }

        throw new RuntimeException('No writable secure environment key provider is available.');
    }

    /**
     * Delete the resolved secure environment key from the provider that supplied it.
     *
     * @param  string  $appPath
     * @return bool
     */
    public function deleteResolved(string $appPath): bool
    {
        $key = $this->find($appPath);

        if (is_null($key) || ! $key->provider instanceof WritesSecureEnvironmentKeys) {
            return false;
        }

        $key->provider->delete($appPath);

        return true;
    }
}
