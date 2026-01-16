<?php

namespace Illuminate\Support\Mapping;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Mapping\Traits\MapFromSnakeCase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

class ObjectMapper
{
    /**
     * Map an array of data to an object of the given class.
     *
     * @template T of object
     *
     * @param  array<array-key, mixed>  $data
     * @param  class-string<T>  $class
     * @return T
     */
    public function map(array $data, string $class): object
    {
        $reflection = new ReflectionClass($class);
        $traits = class_uses_recursive($class);

        if (isset($traits[MapFromSnakeCase::class])) {
            $data = $this->normalizeSnakeCaseKeys($data);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $this->mapToProperties($reflection, $data);
        }

        return $this->mapToConstructor($reflection, $constructor, $data);
    }

    /**
     * Map data to constructor parameters.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  array<string, mixed>  $data
     * @return T
     */
    protected function mapToConstructor(
        ReflectionClass $reflection,
        \ReflectionMethod $constructor,
        array $data
    ): object {
        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $args[] = $this->resolveParameter($param, $data);
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Map data to public properties.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  array<string, mixed>  $data
     * @return T
     */
    protected function mapToProperties(ReflectionClass $reflection, array $data): object
    {
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $data)) {
                $value = $this->coerce($data[$name], $property->getType());
                $property->setValue($instance, $value);
            } elseif (! $property->hasDefaultValue() && ! $property->getType()?->allowsNull()) {
                throw new InvalidArgumentException("Missing required property [{$name}].");
            }
        }

        return $instance;
    }

    /**
     * Resolve a constructor parameter value from the data array.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveParameter(ReflectionParameter $param, array $data): mixed
    {
        $name = $param->getName();
        $type = $param->getType();

        if (array_key_exists($name, $data)) {
            return $this->coerce($data[$name], $type);
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($type?->allowsNull()) {
            return null;
        }

        throw new InvalidArgumentException("Missing required parameter [{$name}].");
    }

    /**
     * Coerce a value to match the expected type.
     */
    protected function coerce(mixed $value, ?ReflectionType $type): mixed
    {
        // No type hint - return as-is
        if ($type === null) {
            return $value;
        }

        // Handle union types (e.g., int|string)
        if ($type instanceof ReflectionUnionType) {
            return $this->coerceUnion($value, $type);
        }

        // Handle named types
        if ($type instanceof ReflectionNamedType) {
            return $this->coerceNamed($value, $type);
        }

        // Intersection types or other - return as-is
        return $value;
    }

    /**
     * Coerce a value for a union type.
     */
    protected function coerceUnion(mixed $value, ReflectionUnionType $type): mixed
    {
        // Try each type in the union until one works
        foreach ($type->getTypes() as $subType) {
            if ($subType instanceof ReflectionNamedType) {
                try {
                    return $this->coerceNamed($value, $subType);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        // If nothing worked, return the original value
        return $value;
    }

    /**
     * Coerce a value to a named type.
     */
    protected function coerceNamed(mixed $value, ReflectionNamedType $type): mixed
    {
        // Handle null
        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }
            throw new InvalidArgumentException('Cannot assign null to non-nullable type.');
        }

        $typeName = $type->getName();

        // Handle built-in types
        return match ($typeName) {
            'int' => $this->coerceInt($value),
            'float' => $this->coerceFloat($value),
            'string' => (string) $value,
            'bool' => $this->coerceBool($value),
            'array' => (array) $value,
            'mixed' => $value,
            'object' => (object) $value,
            default => $this->coerceToClass($value, $typeName),
        };
    }

    /**
     * Coerce a value to an integer.
     */
    protected function coerceInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return (int) $value;
    }

    /**
     * Coerce a value to a float.
     * Handles special float values like Infinity, -Infinity, NaN.
     */
    protected function coerceFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        // Handle special string representations (from Laravel's HasAttributes)
        return match ((string) $value) {
            'Infinity' => INF,
            '-Infinity' => -INF,
            'NaN' => NAN,
            default => (float) $value,
        };
    }

    /**
     * Coerce a value to a boolean.
     * Handles string representations like "true", "false", "yes", "no", "1", "0".
     */
    protected function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }

    /**
     * Coerce a value to a class instance.
     */
    protected function coerceToClass(mixed $value, string $class): mixed
    {
        // Already the correct type
        if ($value instanceof $class) {
            return $value;
        }

        // Handle enums
        if (is_subclass_of($class, UnitEnum::class)) {
            return $this->coerceEnum($value, $class);
        }

        // Handle Carbon/DateTime
        if (is_a($class, CarbonInterface::class, true)) {
            return $this->coerceCarbon($value);
        }

        if (is_a($class, DateTimeInterface::class, true)) {
            return $this->coerceDateTime($value, $class);
        }

        // Handle nested objects (arrays become nested DTOs)
        if (is_array($value) && class_exists($class)) {
            return $this->map($value, $class);
        }

        throw new InvalidArgumentException(
            'Cannot coerce value of type ['.get_debug_type($value)."] to [{$class}]."
        );
    }

    /**
     * Coerce a value to an enum.
     */
    protected function coerceEnum(mixed $value, string $enumClass): BackedEnum|UnitEnum
    {
        // Already an enum instance
        if ($value instanceof $enumClass) {
            return $value;
        }

        // BackedEnum - use from()
        if (is_subclass_of($enumClass, BackedEnum::class)) {
            return $enumClass::from($value);
        }

        // UnitEnum - use constant lookup
        return constant($enumClass.'::'.$value);
    }

    /**
     * Coerce a value to Carbon.
     */
    protected function coerceCarbon(mixed $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return Carbon::parse($value);
    }

    /**
     * Coerce a value to a DateTime instance.
     */
    protected function coerceDateTime(mixed $value, string $class): DateTimeInterface
    {
        if ($value instanceof $class) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            if ($class === DateTimeImmutable::class) {
                return DateTimeImmutable::createFromInterface($value);
            }

            return \DateTime::createFromInterface($value);
        }

        if (is_numeric($value)) {
            $dt = new \DateTime;
            $dt->setTimestamp((int) $value);

            if ($class === DateTimeImmutable::class) {
                return DateTimeImmutable::createFromMutable($dt);
            }

            return $dt;
        }

        if ($class === DateTimeImmutable::class) {
            return new DateTimeImmutable($value);
        }

        return new \DateTime($value);
    }

    /**
     * Convert snake_case keys to camelCase, only for keys containing underscores.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeSnakeCaseKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Only convert if key contains underscore (is snake_case)
            $normalizedKey = str_contains($key, '_')
                ? Str::camel($key)
                : $key;

            // Recursively handle nested arrays
            $result[$normalizedKey] = is_array($value)
                ? $this->normalizeSnakeCaseKeys($value)
                : $value;
        }

        return $result;
    }

}
