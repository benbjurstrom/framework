<?php

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Environment\SecureEnvironmentManager;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;

#[AsCommand(name: 'env:secure')]
class EnvironmentSecureCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'env:secure
                    {variables?* : The environment variables to secure}
                    {--key= : The encryption key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Secure environment values inline and store the encryption key outside the project tree';

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Foundation\Environment\SecureEnvironmentManager|null  $secureEnvironment
     */
    public function __construct(protected ?SecureEnvironmentManager $secureEnvironment = null)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $environmentFilePath = $this->laravel->environmentFilePath();
        $appPath = $this->laravel->basePath();
        $secureEnvironment = $this->secureEnvironment();
        $existingKey = $secureEnvironment->findKey($appPath);
        $containsSecureValues = $secureEnvironment->fileContainsSecureValues($environmentFilePath);
        $variables = $this->argument('variables');

        if ($variables === []) {
            return $this->renderStatus($environmentFilePath, $secureEnvironment, $existingKey);
        }

        ['key' => $key, 'source' => $keySource, 'display' => $displayKey] = $this->resolveEncryptionKey($existingKey, $containsSecureValues);

        if (! is_null($existingKey) && ! $secureEnvironment->keysMatch($existingKey->value, $key)) {
            $this->fail('The provided encryption key does not match the existing secure environment key.');
        }

        try {
            if ($containsSecureValues) {
                $secureEnvironment->ensureKeyCanDecryptEnvironmentFile($environmentFilePath, $key);
            }

            $secureEnvironment->storeKey($appPath, $key);

            $result = $secureEnvironment->secureEnvironmentFile(
                $environmentFilePath,
                $key,
                $variables,
            );
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->components->info('Environment successfully secured.');
        if (! is_null($displayKey)) {
            $this->components->twoColumnDetail('Key', $displayKey);
        } else {
            $this->components->twoColumnDetail('Key source', $keySource);
        }

        $this->components->twoColumnDetail('Cipher', $secureEnvironment->cipherForKey($key));
        $this->components->twoColumnDetail('Environment file', $environmentFilePath);

        $securedVariables = collect($result['secured'])
            ->merge($result['already_secured'])
            ->unique()
            ->values()
            ->all();

        if ($securedVariables !== []) {
            $this->components->twoColumnDetail('Secured', implode(', ', $securedVariables));
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Get the secure environment manager instance.
     *
     * @return \Illuminate\Foundation\Environment\SecureEnvironmentManager
     */
    protected function secureEnvironment()
    {
        return $this->secureEnvironment ??= new SecureEnvironmentManager($this->laravel['files']);
    }

    /**
     * Render the secure environment status.
     *
     * @param  string  $environmentFilePath
     * @param  \Illuminate\Foundation\Environment\SecureEnvironmentManager  $secureEnvironment
     * @param  \Illuminate\Foundation\Environment\SecureEnvironmentKey|null  $existingKey
     * @return int
     */
    protected function renderStatus(string $environmentFilePath, SecureEnvironmentManager $secureEnvironment, $existingKey): int
    {
        $this->components->info('Secure environment status.');

        if (is_null($existingKey)) {
            $this->components->twoColumnDetail('Key source', 'Not found');
        } else {
            $this->components->twoColumnDetail('Key source', $existingKey->source);
            $this->components->twoColumnDetail('Cipher', $secureEnvironment->cipherForKey($existingKey->value));
        }

        $this->components->twoColumnDetail('Environment file', $environmentFilePath);

        $securedVariables = $secureEnvironment->secureVariables($environmentFilePath);

        $this->components->twoColumnDetail(
            'Secured',
            $securedVariables === [] ? 'None' : implode(', ', $securedVariables)
        );

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Resolve the encryption key that should be used for securing values.
     *
     * @param  \Illuminate\Foundation\Environment\SecureEnvironmentKey|null  $existingKey
     * @param  bool  $containsSecureValues
     * @return array{key: string, source: string, display: string|null}
     */
    protected function resolveEncryptionKey($existingKey, bool $containsSecureValues): array
    {
        if (! is_null($key = $this->option('key'))) {
            return [
                'key' => $key,
                'source' => 'Provided via --key',
                'display' => $key,
            ];
        }

        if (! is_null($existingKey)) {
            return [
                'key' => $existingKey->value,
                'source' => $existingKey->source,
                'display' => null,
            ];
        }

        if ($containsSecureValues) {
            if ($this->input->isInteractive()) {
                return [
                    'key' => password('What is the encryption key?'),
                    'source' => 'Provided interactively',
                    'display' => null,
                ];
            }

            $this->fail('An encryption key is required to update an environment file that already contains secure values.');
        }

        if ($this->input->isInteractive()) {
            $ask = select(
                label: 'What encryption key would you like to use?',
                options: [
                    'generate' => 'Generate a random encryption key',
                    'ask' => 'Provide an encryption key',
                ],
                default: 'generate'
            );

            if ($ask === 'ask') {
                $key = password('What is the encryption key?');

                return [
                    'key' => $key,
                    'source' => 'Provided interactively',
                    'display' => $key,
                ];
            }
        }

        $key = $this->secureEnvironment()->generateKey();

        return [
            'key' => $key,
            'source' => 'Generated',
            'display' => $key,
        ];
    }
}
