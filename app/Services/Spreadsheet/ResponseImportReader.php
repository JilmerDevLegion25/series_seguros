<?php

namespace App\Services\Spreadsheet;

interface ResponseImportReader
{
    /**
     * @return iterable<int, list<mixed>>
     */
    public function rows(string $path): iterable;
}
