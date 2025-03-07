<?php declare(strict_types=1);

namespace Flow\ETL\Adapter\Parquet;

use function Flow\ETL\DSL\array_to_rows;
use Flow\ETL\Extractor;
use Flow\ETL\Filesystem\Path;
use Flow\ETL\Filesystem\Stream\Mode;
use Flow\ETL\FlowContext;
use Flow\ETL\Row\EntryFactory;
use Flow\ETL\Row\Factory\NativeEntryFactory;
use Flow\Parquet\ByteOrder;
use Flow\Parquet\Options;
use Flow\Parquet\ParquetFile;
use Flow\Parquet\Reader;

final class ParquetExtractor implements Extractor
{
    /**
     * @param Path $path
     * @param array<string> $columns
     */
    public function __construct(
        private readonly Path $path,
        private readonly Options $options,
        private readonly ByteOrder $byteOrder = ByteOrder::LITTLE_ENDIAN,
        private readonly array $columns = [],
        private readonly int $rowsInBatch = 1000,
        private readonly EntryFactory $entryFactory = new NativeEntryFactory()
    ) {
    }

    public function extract(FlowContext $context) : \Generator
    {
        foreach ($this->readers($context) as $fileData) {
            $rows = [];

            foreach ($fileData['file']->values($this->columns) as $row) {
                if ($context->config->shouldPutInputIntoRows()) {
                    $rows[] = \array_merge(
                        $row,
                        ['_input_file_uri' => $fileData['uri']]
                    );
                } else {
                    $rows[] = $row;
                }

                if (\count($rows) >= $this->rowsInBatch) {
                    yield array_to_rows($rows, $this->entryFactory);
                    $rows = [];
                }
            }

            if (\count($rows)) {
                yield array_to_rows($rows, $this->entryFactory);
            }
        }
    }

    /**
     * @psalm-suppress NullableReturnStatement
     *
     * @return \Generator<int, array{file: ParquetFile, uri: string}>
     */
    private function readers(FlowContext $context) : \Generator
    {
        foreach ($context->streams()->fs()->scan($this->path, $context->partitionFilter()) as $filePath) {
            yield [
                'file' => (new Reader(
                    byteOrder: $this->byteOrder,
                    options: $this->options,
                ))
                    ->readStream($context->streams()->fs()->open($filePath, Mode::READ)->resource()),
                'uri' => $filePath->uri(),
            ];
        }
    }
}
