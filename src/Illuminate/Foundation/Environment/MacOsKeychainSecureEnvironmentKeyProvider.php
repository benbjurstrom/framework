<?php

namespace Illuminate\Foundation\Environment;

use RuntimeException;
use Symfony\Component\Process\Process;

class MacOsKeychainSecureEnvironmentKeyProvider implements WritesSecureEnvironmentKeys
{
    /**
     * Create a new macOS Keychain key provider instance.
     *
     * @param  string  $service
     */
    public function __construct(protected string $service = 'laravel-local-vault')
    {
    }

    /**
     * Get the provider display name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'macOS Keychain';
    }

    /**
     * Retrieve the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @return string|null
     */
    public function get(string $appPath): ?string
    {
        if (! $this->isSupported()) {
            return null;
        }

        $process = new Process([
            'security',
            'find-generic-password',
            '-w',
            '-s',
            $this->service,
            '-a',
            $this->account($appPath),
        ]);

        $process->run();

        if ($process->isSuccessful()) {
            return rtrim($process->getOutput(), "\r\n");
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
        $this->ensureSupported();

        $this->delete($appPath);

        $process = new Process([
            'security',
            'add-generic-password',
            '-U',
            '-s',
            $this->service,
            '-a',
            $this->account($appPath),
            '-w',
            $key,
        ]);

        $process->mustRun();
    }

    /**
     * Delete the secure environment key for the given application path.
     *
     * @param  string  $appPath
     * @return void
     */
    public function delete(string $appPath): void
    {
        $this->ensureSupported();

        $process = new Process([
            'security',
            'delete-generic-password',
            '-s',
            $this->service,
            '-a',
            $this->account($appPath),
        ]);

        $process->run();
    }

    /**
     * Determine if the current platform supports Keychain storage.
     *
     * @return bool
     */
    protected function isSupported(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    /**
     * Ensure the current platform supports Keychain storage.
     *
     * @return void
     */
    protected function ensureSupported(): void
    {
        if (! $this->isSupported()) {
            throw new RuntimeException('The macOS Keychain secure environment provider is only available on macOS.');
        }
    }

    /**
     * Get the account identifier for the given application path.
     *
     * @param  string  $appPath
     * @return string
     */
    protected function account(string $appPath): string
    {
        return realpath($appPath) ?: $appPath;
    }
}
