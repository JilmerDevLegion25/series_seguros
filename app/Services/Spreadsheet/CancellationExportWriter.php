<?php

namespace App\Services\Spreadsheet;

use DateTimeInterface;

interface CancellationExportWriter
{
    /**
     * @param  iterable<int, iterable<int, scalar|DateTimeInterface|null>>  $rows
     */
    public function write(string $path, iterable $rows): void;

    /**
     * @param  iterable<string, iterable<int, iterable<int, scalar|DateTimeInterface|null>>>  $sheets
     */
    public function writeSheets(string $path, iterable $sheets): void;
}
