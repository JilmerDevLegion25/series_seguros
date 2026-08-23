<?php

namespace Tests\Unit;

use App\Services\Spreadsheet\OpenSpoutCancellationExportWriter;
use App\Services\Spreadsheet\OpenSpoutResponseImportReader;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OpenSpoutReadSmokeTest extends TestCase
{
    public function test_reader_opens_xlsx_and_iterates_rows(): void
    {
        $path = $this->temporaryXlsxPath();
        (new OpenSpoutCancellationExportWriter)->write($path, [
            ['Radicado', 'Fecha'],
            ['000123', new DateTimeImmutable('2026-08-19')],
        ]);

        $reader = new OpenSpoutResponseImportReader;
        $rows = iterator_to_array($reader->rows($path), false);

        $this->assertSame('Radicado', $rows[0][0]);
        $this->assertSame('000123', $rows[1][0]);
        $this->assertInstanceOf(DateTimeImmutable::class, $rows[1][1]);

        @unlink($path);
    }

    private function temporaryXlsxPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'openspout-read-');
        $this->assertIsString($path);
        @unlink($path);

        return $path.'.xlsx';
    }
}
