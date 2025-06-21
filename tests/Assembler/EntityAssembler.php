<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use function method_exists;

/**
 * @template T of object
 * @implements \IteratorAggregate<string, mixed>
 */
abstract class EntityAssembler implements \IteratorAggregate
{
    /**
     * @var array<string, mixed>
     */
    protected array $properties = [];

    final public static function new(): static
    {
        $class = static::class;
        /** @var static $instance */
        $instance = new $class();
        return $instance;
    }

    /**
     * @param array<string, mixed> $properties
     */
    final public static function withProperties(array $properties): static
    {
        $assembler = static::new();
        foreach ($properties as $property => $value) {
            $method = 'with' . ucfirst($property);
            if (method_exists($assembler, $method)) {
                $assembler->{$method}($value);
            } else {
                $assembler->properties[$property] = $value;
            }
        }
        return $assembler;
    }

    /**
     * @return $this
     */
    protected function with(string $property, mixed $value): static
    {
        $this->properties[$property] = $value;
        return $this;
    }

    /**
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->properties);
    }

    /**
     * @return T
     */
    abstract public function assemble();
}
