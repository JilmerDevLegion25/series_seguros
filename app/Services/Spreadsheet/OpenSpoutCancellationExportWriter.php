<?php

namespace App\Services\Spreadsheet;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

final class OpenSpoutCancellationExportWriter implements CancellationExportWriter
{
    private const DATE_FORMAT = 'yyyy-mm-dd';

    public function write(string $path, iterable $rows): void
    {
        $this->writeSheets($path, [
            'Export' => $rows,
        ]);
    }

    public function writeSheets(string $path, iterable $sheets): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $isFirstSheet = true;

        try {
            foreach ($sheets as $sheetName => $rows) {
                $sheet = $isFirstSheet
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($this->safeSheetName((string) $sheetName));
                $isFirstSheet = false;

                foreach ($rows as $row) {
                    $writer->addRow($this->makeRow($row));
                }
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * @param  iterable<int, scalar|DateTimeInterface|null>  $row
     */
    private function makeRow(iterable $row): Row
    {
        $values = [];
        $styles = [];

        foreach ($row as $index => $value) {
            $values[] = $value;

            if ($value instanceof DateTimeInterface) {
                $styles[$index] = (new Style)->setFormat(self::DATE_FORMAT);
            }
        }

        return Row::fromValuesWithStyles($values, columnStyles: $styles);
    }

    private function safeSheetName(string $sheetName): string
    {
        $clean = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $sheetName) ?? 'Sheet';
        $clean = trim($clean);

        return substr($clean === '' ? 'Sheet' : $clean, 0, 31);
    }
}
