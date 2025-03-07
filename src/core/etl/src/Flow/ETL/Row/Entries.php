<?php

declare(strict_types=1);

namespace Flow\ETL\Row;

use Flow\ETL\Exception\InvalidArgumentException;
use Flow\ETL\Exception\InvalidLogicException;
use Flow\ETL\Exception\RuntimeException;
use Flow\Serializer\Serializable;

/**
 * @implements \ArrayAccess<string, Entry>
 * @implements \IteratorAggregate<string, Entry>
 * @implements Serializable<array{entries: array<string, Entry>}>
 */
final class Entries implements \ArrayAccess, \Countable, \IteratorAggregate, Serializable
{
    /**
     * @var array<string, Entry>
     */
    private array $entries;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(Entry ...$entries)
    {
        $this->entries = [];

        if ([] !== $entries) {
            foreach ($entries as $entry) {
                $this->entries[$entry->name()] = $entry;
            }

            if (\count($this->entries) !== \count($entries)) {
                throw InvalidArgumentException::because(\sprintf('Entry names must be unique, given: [%s]', \implode(', ', \array_map(fn (Entry $entry) => $entry->name(), $entries))));
            }
        }
    }

    public function __serialize() : array
    {
        return ['entries' => $this->entries];
    }

    public function __unserialize(array $data) : void
    {
        $this->entries = $data['entries'];
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return $this
     */
    public function add(Entry ...$entries) : self
    {
        $newEntries = [];

        foreach ($entries as $entry) {
            $newEntries[$entry->name()] = $entry;
        }

        $mergedEntries = \array_merge($this->entries, $newEntries);

        if (\count($mergedEntries) !== \count($entries) + \count($this->entries)) {
            throw InvalidArgumentException::because(
                \sprintf(
                    'Added entries names must be unique, given: [%s] + [%s]',
                    \implode(', ', \array_map(fn (Entry $entry) => $entry->name(), $this->entries)),
                    \implode(', ', \array_map(fn (Entry $entry) => $entry->name(), $newEntries)),
                )
            );
        }

        return self::recreate($mergedEntries);
    }

    /**
     * @return array<Entry>
     */
    public function all() : array
    {
        return \array_values($this->entries);
    }

    public function count() : int
    {
        return \count($this->entries);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string|EntryReference $name) : Entry
    {
        $entry = $this->find($name);

        if ($entry === null) {
            throw new InvalidArgumentException("Entry \"{$name}\" does not exist. Did you mean one of the following? [\"" . \implode('", "', \array_map(static fn (Entry $entry) => $entry->name(), $this->entries)) . '"]');
        }

        return $entry;
    }

    public function getAll(string|EntryReference ...$names) : self
    {
        $entries = [];

        foreach ($names as $ref) {
            $entries[] = $this->get($ref);
        }

        return new self(...$entries);
    }

    /**
     * @return \Iterator<string, Entry>
     */
    public function getIterator() : \Iterator
    {
        return new \ArrayIterator($this->all());
    }

    public function has(string|EntryReference ...$refs) : bool
    {
        foreach ($refs as $ref) {
            if ($ref instanceof EntryReference) {
                if (!\array_key_exists($ref->name(), $this->entries)) {
                    return false;
                }
            } else {
                if (!\array_key_exists($ref, $this->entries)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isEqual(self $entries) : bool
    {
        if ($this->count() !== $entries->count()) {
            return false;
        }

        foreach ($this->entries as $entry) {
            $otherEntry = $entries->find($entry->ref());

            if ($otherEntry === null) {
                return false;
            }

            if (!$otherEntry->isEqual($entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @template ReturnType
     *
     * @param callable(Entry) : ReturnType $callable
     *
     * @return array<int, ReturnType>
     */
    public function map(callable $callable) : array
    {
        $returnValues = [];

        foreach ($this->entries as $entry) {
            $returnValues[] = $callable($entry);
        }

        return $returnValues;
    }

    public function merge(self $entries) : self
    {
        $newEntries = \array_merge($this->entries, $entries->entries);

        if (\count($newEntries) !== $this->count() + $entries->count()) {
            throw InvalidArgumentException::because(
                \sprintf(
                    'Merged entries names must be unique, given: [%s] + [%s]',
                    \implode(', ', \array_map(fn (Entry $entry) => $entry->name(), $this->entries)),
                    \implode(', ', \array_map(fn (Entry $entry) => $entry->name(), $entries->all())),
                )
            );
        }

        return self::recreate($newEntries);
    }

    /**
     * @param array-key $offset
     *
     * @throws InvalidArgumentException
     */
    public function offsetExists($offset) : bool
    {
        if (!\is_string($offset)) {
            throw new InvalidArgumentException('Entries accepts only string offsets');
        }

        return $this->has($offset);
    }

    /**
     * @param array-key $offset
     *
     * @throws InvalidArgumentException
     */
    public function offsetGet($offset) : Entry
    {
        if (!\is_string($offset)) {
            throw new InvalidArgumentException('Entries accepts only string offsets');
        }

        if ($this->offsetExists($offset)) {
            return $this->get($offset);
        }

        throw new InvalidArgumentException("Entry {$offset} does not exists.");
    }

    public function offsetSet(mixed $offset, mixed $value) : void
    {
        throw new RuntimeException('In order to add new rows use Entries::add(Entry $entry) : self');
    }

    /**
     * @param array-key $offset
     *
     * @throws InvalidArgumentException
     *
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function offsetUnset(mixed $offset) : void
    {
        throw new RuntimeException('In order to add new rows use Entries::remove(string $name) : self');
    }

    public function order(string|EntryReference ...$entries) : self
    {
        $sortedEntries = [];

        if (\count($entries) !== \count($this->entries)) {
            throw InvalidArgumentException::because(
                \sprintf(
                    'In order to sort entries in a given order you need to provide all entry names, given: "%s", expected: "%s"',
                    \implode('", "', \array_map(static fn (EntryReference|string $ref) : string => $ref instanceof EntryReference ? $ref->name() : $ref, $entries)),
                    \implode('", "', \array_map(static fn (Entry $entry) => $entry->name(), $this->entries)),
                )
            );
        }

        foreach ($entries as $ref) {
            if (!$this->has($ref)) {
                throw InvalidArgumentException::because(
                    \sprintf(
                        'There is no entry with name \"%s\" in the dataset, available names: \"%s\"',
                        $ref instanceof EntryReference ? $ref->name() : $ref,
                        \implode('", "', \array_map(static fn (Entry $entry) => $entry->name(), $this->entries)),
                    )
                );
            }

            if ($ref instanceof EntryReference) {
                $sortedEntries[$ref->name()] = $this->entries[$ref->name()];
            } else {
                $sortedEntries[$ref] = $this->entries[$ref];
            }
        }

        return self::recreate($sortedEntries);
    }

    public function remove(string|EntryReference ...$names) : self
    {
        $entries = $this->entries;

        foreach ($names as $ref) {
            if ($this->has($ref) === false) {
                if ($ref instanceof EntryReference) {
                    throw InvalidLogicException::because(\sprintf('Entry "%s" does not exist', $ref->name()));
                }

                throw InvalidLogicException::because(\sprintf('Entry "%s" does not exist', $ref));
            }

            if ($ref instanceof EntryReference) {
                unset($entries[$ref->name()]);
            } else {
                unset($entries[$ref]);
            }
        }

        return self::recreate($entries);
    }

    public function rename(string|EntryReference $currentName, string|EntryReference $newName) : self
    {
        if (!$this->has($currentName)) {
            throw InvalidLogicException::because(\sprintf('Entry "%s" does not exist', $currentName instanceof EntryReference ? $currentName->name() : $currentName));
        }

        $entries = $this->entries;

        $entry = $this->get($currentName);

        if ($currentName instanceof EntryReference) {
            unset($entries[$currentName->name()]);
        } else {
            unset($entries[$currentName]);
        }

        if ($newName instanceof EntryReference) {
            $entries[$newName->name()] = $entry->rename($newName->name());
        } else {
            $entries[$newName] = $entry->rename($newName);
        }

        return self::recreate($entries);
    }

    /**
     * @return $this
     */
    public function set(Entry ...$entries) : self
    {
        $newEntries = $this->entries;

        foreach ($entries as $entry) {
            $newEntries[$entry->name()] = $entry;
        }

        return self::recreate($newEntries);
    }

    public function sort() : self
    {
        $entries = \array_values($this->entries);
        \usort($entries, static fn (Entry $a, Entry $b) => $a->name() <=> $b->name());

        return new self(...$entries);
    }

    /**
     * @psalm-suppress MissingClosureReturnType
     *
     * @return array<string, mixed>
     */
    public function toArray() : array
    {
        return \array_combine(
            $this->map(fn (Entry $entry) => $entry->name()),
            $this->map(fn (Entry $entry) => $entry->value())
        );
    }

    private function find(string|EntryReference $entry) : ?Entry
    {
        if ($this->has($entry)) {
            return $entry instanceof EntryReference ? $this->entries[$entry->name()] : $this->entries[$entry];
        }

        return null;
    }

    /**
     * Internal function used to create entries that are already indexed and validated against duplicates.
     * It comes with a significant performance boost, only to be used inside of this collection.
     *
     * @param array<string, Entry> $entries
     */
    private static function recreate(array $entries) : self
    {
        $instance = new self();
        $instance->entries = $entries;

        return $instance;
    }
}
