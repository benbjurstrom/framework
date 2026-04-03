<?php

namespace Illuminate\Tests\Foundation\Bootstrap;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Str;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LoadEnvironmentVariablesTest extends TestCase
{
    protected array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY'], $_ENV['DB_PASSWORD'], $_ENV['FOO'], $_SERVER['APP_KEY'], $_SERVER['DB_PASSWORD'], $_SERVER['FOO'], $_SERVER['LARAVEL_ENV_SECURE_KEY']);
        putenv('FOO');
        putenv('APP_KEY');
        putenv('DB_PASSWORD');
        putenv('LARAVEL_ENV_SECURE_KEY');

        foreach ($this->temporaryDirectories as $directory) {
            @unlink($directory.'/.env');
            @rmdir($directory);
        }

        parent::tearDown();
    }

    protected function getAppMock($file, ?string $path = null)
    {
        $path ??= __DIR__.'/../fixtures';

        $app = m::mock(Application::class);

        $app->shouldReceive('configurationIsCached')
            ->once()->with()->andReturn(false);
        $app->shouldReceive('runningInConsole')
            ->once()->with()->andReturn(false);
        $app->shouldReceive('environmentPath')
            ->once()->with()->andReturn($path);
        $app->shouldReceive('environmentFile')
            ->once()->with()->andReturn($file);
        $app->shouldReceive('environmentFilePath')
            ->once()->with()->andReturn($path.'/'.$file);
        $app->shouldReceive('basePath')
            ->once()->with()->andReturn($path);

        return $app;
    }

    public function testCanLoad()
    {
        $this->expectOutputString('');

        (new LoadEnvironmentVariables)->bootstrap($this->getAppMock('.env'));

        $this->assertSame('BAR', env('FOO'));
        $this->assertSame('BAR', getenv('FOO'));
        $this->assertSame('BAR', $_ENV['FOO']);
        $this->assertSame('BAR', $_SERVER['FOO']);
    }

    public function testCanFailSilent()
    {
        $this->expectOutputString('');

        (new LoadEnvironmentVariables)->bootstrap($this->getAppMock('BAD_FILE'));
    }

    public function testItCanLoadSecureEnvironmentValues(): void
    {
        $directory = $this->createTemporaryEnvironmentDirectory();
        $secureKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
        $appKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));

        $_SERVER['LARAVEL_ENV_SECURE_KEY'] = $secureKey;

        $encrypter = new Encrypter(base64_decode(Str::after($secureKey, 'base64:')), 'AES-256-CBC');

        file_put_contents($directory.'/.env', implode(PHP_EOL, [
            'APP_KEY=secure:'.$encrypter->encryptString($appKey),
            'DB_PASSWORD=secure:'.$encrypter->encryptString('"secret-value"'),
        ]));

        (new LoadEnvironmentVariables)->bootstrap($this->getAppMock('.env', $directory));

        $this->assertSame($appKey, env('APP_KEY'));
        $this->assertSame('secret-value', env('DB_PASSWORD'));
    }

    public function testItFailsWhenSecureEnvironmentValuesCannotBeResolved(): void
    {
        $directory = $this->createTemporaryEnvironmentDirectory();
        $secureKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
        $appKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
        $encrypter = new Encrypter(base64_decode(Str::after($secureKey, 'base64:')), 'AES-256-CBC');

        file_put_contents($directory.'/.env', 'APP_KEY=secure:'.$encrypter->encryptString($appKey));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secure environment values are present');

        (new LoadEnvironmentVariables)->bootstrap($this->getAppMock('.env', $directory));
    }

    protected function createTemporaryEnvironmentDirectory(): string
    {
        $directory = sys_get_temp_dir().'/laravel-secure-env-test-'.Str::random();

        mkdir($directory);

        $this->temporaryDirectories[] = $directory;

        return $directory;
    }
}
