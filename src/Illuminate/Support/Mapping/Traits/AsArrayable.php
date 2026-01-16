<?php

namespace Illuminate\Support\Mapping\Traits;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use UnitEnum;

/**
 * Adds toArray() method that returns all public properties as an array.
 *
 * Implements Laravel's Arrayable contract for seamless integration.
 *
 * @example
 * class UserData
 * {
 *     use AsArrayable;
 *
 *     public function __construct(
 *         public readonly string $firstName,
 *         public readonly string $email,
 *     ) {}
 * }
 *
 * $data = new UserData('John', 'john@example.com');
 * $data->toArray(); // ['firstName' => 'John', 'email' => 'john@example.com']
 *
 * @implements Arrayable<string, mixed>
 */
trait AsArrayable
{
    /**
     * Convert the object to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach (get_object_vars($this) as $key => $value) {
            $result[$key] = $this->convertValueToArray($value);
        }

        return $result;
    }

    /**
     * Convert a value to its array representation if applicable.
     */
    protected function convertValueToArray(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->convertValueToArray($item), $value);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.vp');
        }

        return $value;
    }
}
