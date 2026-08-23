<?php

namespace Tests\Unit;

use App\Services\Spreadsheet\OpenSpoutCancellationExportWriter;
use DateTimeImmutable;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\TestCase;

final class OpenSpoutWriteSmokeTest extends TestCase
{
    public function test_writer_creates_xlsx_with_text_and_dates(): void
    {
        $path = $this->temporaryXlsxPath();

        (new OpenSpoutCancellationExportWriter)->write($path, [
            ['Radicado', 'Fecha'],
            ['000456', new DateTimeImmutable('2026-08-19')],
        ]);

        $this->assertFileExists($path);

        $reader = new Reader;
        $reader->open($path);

        try {
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(
                        static fn ($cell): mixed => $cell->getValue(),
                        $row->getCells(),
                    );
                }

                break;
            }
        } finally {
            $reader->close();
        }

        $this->assertSame('000456', $rows[1][0]);
        $this->assertInstanceOf(DateTimeImmutable::class, $rows[1][1]);

        @unlink($path);
    }

    private function temporaryXlsxPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'openspout-write-');
        $this->assertIsString($path);
        @unlink($path);

        return $path.'.xlsx';
    }
}
