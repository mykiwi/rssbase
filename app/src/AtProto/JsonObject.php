<?php

declare(strict_types=1);

namespace App\AtProto;

/**
 * A JSON object sent by a server of the network, whose shape is checked at each read: any server can
 * answer anything.
 */
final readonly class JsonObject
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {}

    public function string(string $key): ?string
    {
        return \is_string($this->data[$key] ?? null) ? $this->data[$key] : null;
    }

    public function int(string $key): ?int
    {
        return \is_int($this->data[$key] ?? null) ? $this->data[$key] : null;
    }

    public function isTrue(string $key): bool
    {
        return true === ($this->data[$key] ?? null);
    }

    public function object(string $key): ?self
    {
        return \is_array($this->data[$key] ?? null) ? new self($this->data[$key]) : null;
    }

    /**
     * @return list<string> the strings of the array, without its other values
     */
    public function strings(string $key): array
    {
        return (
            \is_array($this->data[$key] ?? null) ? array_values(array_filter($this->data[$key], \is_string(...))) : []
        );
    }

    /**
     * @return list<self> the objects of the array, without its other values
     */
    public function objects(string $key): array
    {
        if (!\is_array($this->data[$key] ?? null)) {
            return [];
        }

        return array_values(array_map(
            static fn(array $object): self => new self($object),
            array_filter($this->data[$key], \is_array(...)),
        ));
    }
}
