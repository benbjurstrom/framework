<?php

namespace Illuminate\Support\Mapping\Traits;

/**
 * Marker trait that tells ObjectMapper to convert snake_case input keys to camelCase.
 *
 * Smart conversion: Only keys containing underscores are converted, others are left untouched.
 * This handles mixed-convention inputs gracefully.
 *
 * @example
 * class CreateUserData
 * {
 *     use MapFromSnakeCase;
 *
 *     public function __construct(
 *         public readonly string $firstName,  // ← mapped from 'first_name'
 *         public readonly string $email,      // ← mapped from 'email' (no conversion)
 *     ) {}
 * }
 */
trait MapFromSnakeCase
{
    //
}
