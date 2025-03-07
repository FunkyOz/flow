<?php

declare(strict_types=1);

namespace Flow\ETL\Row\Reference\Expression;

use Flow\ETL\Exception\InvalidArgumentException;
use Flow\ETL\Row;
use Flow\ETL\Row\Reference\Expression;

final class Cast implements Expression
{
    public function __construct(
        private readonly Expression $ref,
        private readonly string $type
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws \JsonException
     */
    public function eval(Row $row) : mixed
    {
        /** @psalm-suppress MixedAssignment */
        $value = $this->ref->eval($row);

        if (null === $value) {
            return null;
        }

        return match (\mb_strtolower($this->type)) {
            'datetime' => match (\gettype($value)) {
                'string' => new \DateTimeImmutable($value),
                'integer' => \DateTimeImmutable::createFromFormat('U', (string) $value),
                default => null,
            },
            'date' => match (\gettype($value)) {
                'string' => (new \DateTimeImmutable($value))->setTime(0, 0, 0, 0),
                'integer' => \DateTimeImmutable::createFromFormat('U', (string) $value),
                default => null,
            },
            /** @phpstan-ignore-next-line */
            'int', 'integer' => (int) $value,
            /** @phpstan-ignore-next-line */
            'float', 'double', 'real' => (float) $value,
            'string' => $this->toString($value),
            'bool', 'boolean' => (bool) $value,
            'array' => $this->toArray($value),
            'object' => (object) $value,
            'json' => \json_encode($value, JSON_THROW_ON_ERROR),
            'json_pretty' => \json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            'xml' => $this->toXML($value),
            default => null
        };
    }

    private function toArray(mixed $data) : array
    {
        if ($data instanceof \DOMDocument) {
            return (new Cast\XMLConverter())->toArray($data);
        }

        return (array) $data;
    }

    private function toString(mixed $value) : ?string
    {
        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            return $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_array($value)) {
            return \json_encode($value, JSON_THROW_ON_ERROR);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::RFC3339);
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if ($value instanceof \DOMDocument) {
            return $value->saveXML() ?: null;
        }

        /** @phpstan-ignore-next-line */
        return (string) $value;
    }

    private function toXML(mixed $value) : null|\DOMDocument
    {
        if (\is_string($value)) {
            $doc = new \DOMDocument();

            if (!@$doc->load($value)) {
                return null;
            }

            return $doc;
        }

        if ($value instanceof \DOMDocument) {
            return $value;
        }

        return null;
    }
}
