<?php

declare(strict_types=1);

namespace Flow\ETL\Pipeline;

use Flow\ETL\Loader;
use Flow\ETL\Transformer;
use Flow\Serializer\Serializable;

/**
 * @implements Serializable<array{pipes: array<int, Loader|Transformer>}>
 */
final class Pipes implements Serializable
{
    /**
     * @param array<int, Loader|Transformer> $pipes
     */
    public function __construct(private array $pipes)
    {
    }

    public static function empty() : self
    {
        return new self([]);
    }

    public function __serialize() : array
    {
        return [
            'pipes' => $this->pipes,
        ];
    }

    public function __unserialize(array $data) : void
    {
        $this->pipes = $data['pipes'];
    }

    public function add(Loader|Transformer $pipe) : void
    {
        $this->pipes[] = $pipe;
    }

    /**
     * @return array<Loader|Transformer>
     */
    public function all() : array
    {
        return $this->pipes;
    }

    public function has(string $transformerClass) : bool
    {
        if (!\class_exists($transformerClass)) {
            return false;
        }

        if (!\is_subclass_of($transformerClass, Transformer::class)) {
            return false;
        }

        foreach ($this->pipes as $pipe) {
            if ($pipe instanceof $transformerClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<Loader>
     */
    public function loaders() : array
    {
        $loaders = [];

        foreach ($this->pipes as $pipe) {
            if ($pipe instanceof Loader) {
                $loaders[] = $pipe;
            }
        }

        return $loaders;
    }
}
