<?php

namespace App\Services\Spreadsheet;

use OpenSpout\Reader\XLSX\Reader;

final class OpenSpoutResponseImportReader implements ResponseImportReader
{
    public function rows(string $path): iterable
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield array_map(
                        static fn ($cell): mixed => $cell->getValue(),
                        $row->getCells(),
                    );
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }
}
