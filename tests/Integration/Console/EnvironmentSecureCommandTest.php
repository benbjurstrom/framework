<?php

namespace Illuminate\Tests\Integration\Console;

use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Environment\SecureEnvironmentKeyRepository;
use Illuminate\Foundation\Environment\SecureEnvironmentManager;
use Illuminate\Foundation\Environment\WritesSecureEnvironmentKeys;
use Orchestra\Testbench\TestCase;

class EnvironmentSecureCommandTest extends TestCase
{
    protected Filesystem $files;

    protected string $environmentFilePath;

    protected ?string $originalEnvironmentContents = null;

    protected bool $environmentFileExisted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->environmentFilePath = $this->app->environmentFilePath();
        $this->environmentFileExisted = $this->files->exists($this->environmentFilePath);
        $this->originalEnvironmentContents = $this->environmentFileExisted
            ? $this->files->get($this->environmentFilePath)
            : null;
    }

    protected function tearDown(): void
    {
        unset($_ENV['LARAVEL_ENV_SECURE_KEY'], $_SERVER['LARAVEL_ENV_SECURE_KEY']);
        putenv('LARAVEL_ENV_SECURE_KEY');

        if ($this->environmentFileExisted) {
            $this->files->put($this->environmentFilePath, $this->originalEnvironmentContents);
        } else {
            $this->files->delete($this->environmentFilePath);
        }

        parent::tearDown();
    }

    public function testItSecuresTheApplicationKeyAndRequestedVariables(): void
    {
        $secureKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
        $appKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));

        $this->files->put($this->environmentFilePath, implode(PHP_EOL, [
            'APP_KEY='.$appKey,
            'DB_PASSWORD="secret-value"',
            'APP_NAME=Laravel',
        ]));

        $provider = new ArraySecureEnvironmentKeyProvider;

        $this->app->instance(SecureEnvironmentManager::class, new SecureEnvironmentManager(
            $this->files,
            new SecureEnvironmentKeyRepository([$provider]),
        ));

        $this->artisan('env:secure', ['variables' => ['DB_PASSWORD'], '--key' => $secureKey])
            ->expectsOutputToContain('Environment successfully secured.')
            ->expectsOutputToContain($secureKey)
            ->expectsOutputToContain('AES-256-CBC')
            ->assertSuccessful();

        $contents = $this->files->get($this->environmentFilePath);

        $this->assertStringContainsString('APP_KEY=secure:', $contents);
        $this->assertStringContainsString('DB_PASSWORD=secure:', $contents);
        $this->assertStringContainsString('APP_NAME=Laravel', $contents);
        $this->assertSame($secureKey, $provider->get($this->app->basePath()));
    }

    public function testItPromptsForAnEncryptionKeyLikeEnvEncrypt(): void
    {
        $appKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));

        $this->files->put($this->environmentFilePath, implode(PHP_EOL, [
            'APP_KEY='.$appKey,
            'DB_PASSWORD="secret-value"',
        ]));

        $provider = new ArraySecureEnvironmentKeyProvider;

        $this->app->instance(SecureEnvironmentManager::class, new SecureEnvironmentManager(
            $this->files,
            new SecureEnvironmentKeyRepository([$provider]),
        ));

        $this->artisan('env:secure', ['variables' => ['DB_PASSWORD']])
            ->expectsQuestion('What encryption key would you like to use?', 'generate')
            ->expectsOutputToContain('Environment successfully secured.')
            ->expectsOutputToContain('Cipher')
            ->assertSuccessful();

        $this->assertNotNull($provider->get($this->app->basePath()));
    }

    public function testItShowsStatusWhenNoVariablesAreSpecified(): void
    {
        $secureKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
        $appKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));

        $this->files->put($this->environmentFilePath, implode(PHP_EOL, [
            'APP_KEY='.$appKey,
            'DB_PASSWORD="secret-value"',
        ]));

        $provider = new ArraySecureEnvironmentKeyProvider;
        $manager = new SecureEnvironmentManager(
            $this->files,
            new SecureEnvironmentKeyRepository([$provider]),
        );

        $manager->storeKey($this->app->basePath(), $secureKey);
        $manager->secureEnvironmentFile($this->environmentFilePath, $secureKey, ['DB_PASSWORD']);

        $this->app->instance(SecureEnvironmentManager::class, $manager);

        $this->artisan('env:secure')
            ->expectsOutputToContain('Secure environment status.')
            ->expectsOutputToContain('Test provider')
            ->expectsOutputToContain('APP_KEY, DB_PASSWORD')
            ->doesntExpectOutputToContain($secureKey)
            ->assertSuccessful();
    }

    public function testItDoesNotPrintAnExistingKeyValueWhenSecuringVariables(): void
    {
        $secureKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
        $appKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));

        $this->files->put($this->environmentFilePath, implode(PHP_EOL, [
            'APP_KEY='.$appKey,
            'DB_PASSWORD="secret-value"',
            'STRIPE_SECRET="stripe-secret"',
        ]));

        $provider = new ArraySecureEnvironmentKeyProvider;
        $manager = new SecureEnvironmentManager(
            $this->files,
            new SecureEnvironmentKeyRepository([$provider]),
        );

        $manager->storeKey($this->app->basePath(), $secureKey);
        $manager->secureEnvironmentFile($this->environmentFilePath, $secureKey, ['DB_PASSWORD']);

        $this->app->instance(SecureEnvironmentManager::class, $manager);

        $this->artisan('env:secure', ['variables' => ['STRIPE_SECRET']])
            ->expectsOutputToContain('Environment successfully secured.')
            ->expectsOutputToContain('Key source')
            ->expectsOutputToContain('Test provider')
            ->doesntExpectOutputToContain($secureKey)
            ->assertSuccessful();
    }
}

class ArraySecureEnvironmentKeyProvider implements WritesSecureEnvironmentKeys
{
    protected array $keys = [];

    public function name(): string
    {
        return 'Test provider';
    }

    public function get(string $appPath): ?string
    {
        return $this->keys[$appPath] ?? null;
    }

    public function store(string $appPath, string $key): void
    {
        $this->keys[$appPath] = $key;
    }

    public function delete(string $appPath): void
    {
        unset($this->keys[$appPath]);
    }
}
