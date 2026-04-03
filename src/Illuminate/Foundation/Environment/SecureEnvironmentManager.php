<?php

namespace Illuminate\Foundation\Environment;

use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SecureEnvironmentManager
{
    /**
     * The secure environment prefix.
     *
     * @var string
     */
    public const PREFIX = 'secure:';

    /**
     * Create a new secure environment manager instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \Illuminate\Foundation\Environment\SecureEnvironmentKeyRepository|null  $keys
     */
    public function __construct(
        protected Filesystem $files,
        protected ?SecureEnvironmentKeyRepository $keys = null,
    ) {
        $this->keys ??= SecureEnvironmentKeyRepository::createDefault();
    }

    /**
     * Determine if the given environment file contains secure values.
     *
     * @param  string  $path
     * @return bool
     */
    public function fileContainsSecureValues(string $path): bool
    {
        return $this->files->exists($path)
            && $this->containsSecureValues($this->files->get($path));
    }

    /**
     * Retrieve the secure environment encryption key for the given application path.
     *
     * @param  string  $appPath
     * @return string|null
     */
    public function getKey(string $appPath): ?string
    {
        return $this->keys->get($appPath);
    }

    /**
     * Retrieve the secure environment encryption key and its source for the given application path.
     *
     * @param  string  $appPath
     * @return \Illuminate\Foundation\Environment\SecureEnvironmentKey|null
     */
    public function findKey(string $appPath): ?SecureEnvironmentKey
    {
        return $this->keys->find($appPath);
    }

    /**
     * Store the secure environment encryption key for the given application path.
     *
     * @param  string  $appPath
     * @param  string  $key
     * @return void
     */
    public function storeKey(string $appPath, string $key): void
    {
        $this->keys->store($appPath, $key);
    }

    /**
     * Delete the secure environment encryption key for the given application path.
     *
     * @param  string  $appPath
     * @return void
     */
    public function deleteKey(string $appPath): void
    {
        $this->keys->delete($appPath);
    }

    /**
     * Delete the resolved secure environment encryption key from its source, if possible.
     *
     * @param  string  $appPath
     * @return bool
     */
    public function deleteResolvedKey(string $appPath): bool
    {
        return $this->keys->deleteResolved($appPath);
    }

    /**
     * Generate a new secure environment encryption key.
     *
     * @return string
     */
    public function generateKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
    }

    /**
     * Get the list of secured variables in the given environment file.
     *
     * @param  string  $environmentFilePath
     * @return array<int, string>
     */
    public function secureVariables(string $environmentFilePath): array
    {
        if (! $this->files->exists($environmentFilePath)) {
            return [];
        }

        preg_match_all('/^([A-Za-z_][A-Za-z0-9_]*)=secure:/m', $this->files->get($environmentFilePath), $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Determine the cipher used by the given key.
     *
     * @param  string  $key
     * @return string
     */
    public function cipherForKey(string $key): string
    {
        return $this->inferCipher($this->parseKey($key));
    }

    /**
     * Determine if the given encryption keys are equivalent.
     *
     * @param  string  $first
     * @param  string  $second
     * @return bool
     */
    public function keysMatch(string $first, string $second): bool
    {
        return hash_equals($this->parseKey($first), $this->parseKey($second));
    }

    /**
     * Create a temporary decrypted environment file for loading.
     *
     * @param  string  $appPath
     * @param  string  $environmentFilePath
     * @return string|null
     */
    public function createTemporaryEnvironmentFile(string $appPath, string $environmentFilePath): ?string
    {
        if (! $this->files->exists($environmentFilePath)) {
            return null;
        }

        $contents = $this->files->get($environmentFilePath);

        if (! $this->containsSecureValues($contents)) {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'laravel-secure-env-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary environment file.');
        }

        $key = $this->getKey($appPath);

        if (is_null($key)) {
            throw new RuntimeException('Secure environment values are present, but no encryption key was found in the macOS Keychain or the [LARAVEL_ENV_SECURE_KEY] environment variable.');
        }

        $this->files->put($temporaryPath, $this->decryptSecureValues($contents, $key));

        return $temporaryPath;
    }

    /**
     * Secure the given environment file.
     *
     * @param  string  $environmentFilePath
     * @param  string  $key
     * @param  array<int, string>  $variables
     * @return array{secured: array<int, string>, already_secured: array<int, string>}
     */
    public function secureEnvironmentFile(string $environmentFilePath, string $key, array $variables = []): array
    {
        $contents = $this->files->get($environmentFilePath);
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        $appKeyLine = $this->findVariable($lines, 'APP_KEY');

        if (is_null($appKeyLine)) {
            throw new RuntimeException('Unable to secure the environment file. No APP_KEY variable was found.');
        }

        $variables = collect($variables)
            ->prepend('APP_KEY')
            ->unique()
            ->values()
            ->all();

        $secured = [];
        $alreadySecured = [];
        $encrypter = $this->createEncrypter($key);

        foreach ($variables as $variable) {
            $line = $this->findVariable($lines, $variable);

            if (is_null($line)) {
                throw new RuntimeException("Unable to secure the environment file. No [{$variable}] variable was found.");
            }

            if (Str::startsWith($line['value'], self::PREFIX)) {
                $alreadySecured[] = $variable;

                continue;
            }

            $lines[$line['index']] = $variable.'='.self::PREFIX.$encrypter->encryptString($line['value']);
            $secured[] = $variable;
        }

        $this->files->put($environmentFilePath, implode(PHP_EOL, $lines));

        return [
            'secured' => $secured,
            'already_secured' => $alreadySecured,
        ];
    }

    /**
     * Unsecure the given environment file.
     *
     * @param  string  $appPath
     * @param  string  $environmentFilePath
     * @param  array<int, string>  $variables
     * @return array{unsecured: array<int, string>, key_deleted: bool, key_source: string|null}
     */
    public function unsecureEnvironmentFile(string $appPath, string $environmentFilePath, array $variables = []): array
    {
        $contents = $this->files->get($environmentFilePath);

        if (! $this->containsSecureValues($contents)) {
            return ['unsecured' => [], 'key_deleted' => false, 'key_source' => null];
        }

        $resolvedKey = $this->findKey($appPath);
        $key = $resolvedKey?->value;

        if (is_null($key)) {
            throw new RuntimeException('Secure environment values are present, but no encryption key was found in the macOS Keychain or the [LARAVEL_ENV_SECURE_KEY] environment variable.');
        }

        $variables = array_values(array_unique($variables));
        $unsecured = [];

        $decrypted = preg_replace_callback('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/m', function (array $matches) use ($key, $variables, &$unsecured) {
            if ($variables !== [] && ! in_array($matches[1], $variables, true)) {
                return $matches[0];
            }

            $value = $matches[2];

            if (! Str::startsWith($value, self::PREFIX)) {
                return $matches[0];
            }

            $unsecured[] = $matches[1];

            return $matches[1].'='.$this->decryptValue(Str::after($value, self::PREFIX), $key);
        }, $contents);

        if (is_null($decrypted)) {
            throw new RuntimeException('Unable to unsecure the environment file.');
        }

        $this->files->put($environmentFilePath, $decrypted);

        $keyDeleted = false;

        if (! $this->containsSecureValues($decrypted)) {
            $keyDeleted = $this->deleteResolvedKey($appPath);
        }

        return [
            'unsecured' => array_values(array_unique($unsecured)),
            'key_deleted' => $keyDeleted,
            'key_source' => $resolvedKey?->source,
        ];
    }

    /**
     * Ensure the provided key can decrypt the secure values in the given environment file.
     *
     * @param  string  $environmentFilePath
     * @param  string  $key
     * @return void
     */
    public function ensureKeyCanDecryptEnvironmentFile(string $environmentFilePath, string $key): void
    {
        if (! $this->files->exists($environmentFilePath)) {
            return;
        }

        $contents = $this->files->get($environmentFilePath);

        if (! $this->containsSecureValues($contents)) {
            return;
        }

        $this->decryptSecureValues($contents, $key);
    }

    /**
     * Decrypt all secure values in the given environment contents.
     *
     * @param  string  $contents
     * @param  string  $key
     * @return string
     */
    protected function decryptSecureValues(string $contents, string $key): string
    {
        $decrypted = preg_replace_callback('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/m', function (array $matches) use ($key) {
            $value = $matches[2];

            if (! Str::startsWith($value, self::PREFIX)) {
                return $matches[0];
            }

            return $matches[1].'='.$this->decryptValue(Str::after($value, self::PREFIX), $key);
        }, $contents);

        if (is_null($decrypted)) {
            throw new RuntimeException('Unable to decrypt secure environment values.');
        }

        return $decrypted;
    }

    /**
     * Determine if the given environment contents contain secure values.
     *
     * @param  string  $contents
     * @return bool
     */
    protected function containsSecureValues(string $contents): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*=secure:/m', $contents) === 1;
    }

    /**
     * Decrypt an individual secure value.
     *
     * @param  string  $payload
     * @param  string  $key
     * @return string
     */
    protected function decryptValue(string $payload, string $key): string
    {
        try {
            return $this->createEncrypter($key)->decryptString($payload);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to decrypt a secure environment value. The encryption key may be incorrect or the ciphertext may be corrupted.', 0, $e);
        }
    }

    /**
     * Find the given variable in the environment file lines.
     *
     * @param  array<int, string>  $lines
     * @param  string  $name
     * @return array{index: int, value: string}|null
     */
    protected function findVariable(array $lines, string $name): ?array
    {
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, $name.'=')) {
                return ['index' => $index, 'value' => substr($line, strlen($name) + 1)];
            }
        }

        return null;
    }

    /**
     * Create an encrypter from the given secure environment key.
     *
     * @param  string  $key
     * @return \Illuminate\Encryption\Encrypter
     */
    protected function createEncrypter(string $key): Encrypter
    {
        $parsedKey = $this->parseKey($key);

        return new Encrypter($parsedKey, $this->inferCipher($parsedKey));
    }

    /**
     * Parse the given secure environment key.
     *
     * @param  string  $key
     * @return string
     */
    protected function parseKey(string $key): string
    {
        $parsedKey = Str::startsWith($key, 'base64:')
            ? base64_decode(Str::after($key, 'base64:'), true)
            : $key;

        if ($parsedKey === false) {
            throw new RuntimeException('The secure environment encryption key is invalid.');
        }

        return $parsedKey;
    }

    /**
     * Infer the cipher from the parsed key length.
     *
     * @param  string  $parsedKey
     * @return string
     */
    protected function inferCipher(string $parsedKey): string
    {
        return match (mb_strlen($parsedKey, '8bit')) {
            16 => 'AES-128-CBC',
            32 => 'AES-256-CBC',
            default => throw new RuntimeException('The secure environment encryption key has an unsupported length.'),
        };
    }
}
