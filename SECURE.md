# Secure Environment Variables

## Introduction

Laravel's secure environment variables feature allows you to keep sensitive values out of plaintext `.env` files during local development.

When a value is secured, it remains in your environment file using the `secure:` prefix, but its contents are encrypted:

```dotenv
APP_KEY=secure:eyJpdiI6IjR...
APP_NAME=Laravel
DB_CONNECTION=mysql
DB_PASSWORD=secure:eyJpdiI6Ik1...
STRIPE_SECRET=secure:eyJpdiI6InN...
```

At boot time, Laravel decrypts secured values in memory and loads them into the environment as usual.

> [!NOTE]
> This feature is intended for local development hardening. It helps reduce passive disclosure of secrets stored in your project tree, but it is not a replacement for a production secrets manager.

## How It Works

Secure environment variables use a dedicated encryption key to protect secured values, including your application's `APP_KEY`.

When Laravel detects `secure:` values in your environment file, it will attempt to resolve the secure environment key from the following sources:

- The macOS Keychain.
- The `LARAVEL_ENV_SECURE_KEY` environment variable.

If Laravel cannot resolve the key, or if a secured value cannot be decrypted, an exception will be thrown and the application will fail to boot.

## Securing Environment Variables

To view the current secure environment status, run the `env:secure` command without any variable names:

```shell
php artisan env:secure
```

When run without arguments, Laravel will display status information such as:

- Whether a secure environment key is available.
- Where the key was resolved from.
- Which environment variables are currently secured.

To secure your application's `APP_KEY`, run the `env:secure` command and provide the variables you would like to secure:

```shell
php artisan env:secure APP_KEY
```

When this command is executed, Laravel will:

- Resolve or generate a secure environment encryption key.
- Store the key in the macOS Keychain.
- Encrypt the `APP_KEY` value inline in your `.env` file using the `secure:` prefix.

If Laravel needs to generate a new key interactively, it will prompt you in the same style as the `env:encrypt` command:

```shell
php artisan env:secure DB_PASSWORD
```

You may also provide the encryption key explicitly:

```shell
php artisan env:secure --key="base64:..."
```

You may also provide one or more additional environment variable names to secure at the same time:

```shell
php artisan env:secure DB_PASSWORD STRIPE_SECRET AWS_SECRET_ACCESS_KEY
```

Only the variables you explicitly specify will be secured. Laravel does not attempt to guess which variables should be treated as sensitive.

> [!NOTE]
> Laravel only secures the variables you explicitly provide to the command.

## One-Way Hardening

For this proof of concept, securing environment values is intentionally one-way.

Laravel does not provide an `env:unsecure` command. This helps prevent a rogue or compromised agent with Artisan access from rewriting secured values back into plaintext inside your project tree.

If you need to change which values are secured, you should continue using `env:secure` to secure additional variables. If you need to recover plaintext values, you should rely on your existing secret source rather than exporting them back into `.env` through the framework.

## Key Storage

On macOS, Laravel stores the secure environment key in the Keychain and uses it as the source of truth for decrypting secured values.

The current implementation stores the key using:

- Service: `laravel-local-vault`
- Account: the real path to the application base directory

This allows Laravel to associate a stored key with a specific application on your machine.

## Environment Variable Fallback

If the secure environment key is not available from the macOS Keychain, Laravel will attempt to read it from the `LARAVEL_ENV_SECURE_KEY` environment variable.

This fallback is primarily useful in situations where Keychain access is unavailable or when you need to bootstrap an already secured environment temporarily.

For example, you may export the key in your shell before starting the application:

```shell
export LARAVEL_ENV_SECURE_KEY='base64:...'
php artisan serve
```

## Configuration Caching

Configuration caching is not compatible with secured environment values.

If your environment file contains any `secure:` values, the `config:cache` command will fail with an exception.

This prevents decrypted secrets from being written to the configuration cache file.

## Security Considerations

Secure environment variables are designed to protect against passive disclosure, such as:

- Secrets being exposed through routine file inspection.
- AI agents reading plaintext values directly from the project tree.
- Accidental disclosure during local development workflows.

However, they do not protect against arbitrary code execution or a fully compromised machine. Code that is already running within your application may still access decrypted environment values after boot.

## Current Scope

The current implementation is intentionally narrow:

- It is designed for local development.
- It uses a dedicated encryption key for securing environment values.
- It supports the macOS Keychain as the primary key provider.
- It supports `LARAVEL_ENV_SECURE_KEY` as an environment-based fallback.
- It provides an `env:secure` command.
- It does not automatically detect sensitive variables.
- It does not support configuration caching while secure values are present.
