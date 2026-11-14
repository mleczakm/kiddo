<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

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

    final public function __construct() {}

    final public static function new(): static
    {
        $class = static::class;
        /** @var static $instance */
        return new $class();
    }

    /**
     * @param array<string, mixed> $properties
     */
    final public static function withProperties(array $properties): static
    {
        $assembler = static::new();
        $assembler->properties = $properties;
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
    #[\Override]
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->properties);
    }

    /**
     * @return T
     */
    abstract public function assemble();
}
