<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;

class ReportSheetExport implements FromCollection, WithCustomStartCell, WithDrawings, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        private string $label,
        private Collection $rows,
        private array $tempFiles = []
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn($row) => collect((array) $row)->values());
    }

    public function headings(): array
    {
        if ($this->rows->isEmpty()) {
            return [];
        }

        return array_map(
            fn($key) => strtoupper(str_replace('_', ' ', $key)),
            array_keys((array) $this->rows->first())
        );
    }

    public function title(): string
    {
        return Str::limit(preg_replace('/[:\\\\\/?*\[\]]/', '', $this->label), 31, '');
    }

    public function startCell(): string
    {
        $imageRows = count($this->tempFiles) * 14 + 2;
        return 'A' . max(1, $imageRows);
    }

    public function drawings(): array
    {
        $drawings = [];
        $rowOffset = 1;
        foreach ($this->tempFiles as $type => $path) {
            if (!file_exists($path)) continue;
            try {
                $drawing = new Drawing();
                $drawing->setName('Chart - ' . $type);
                $drawing->setPath($path);
                $drawing->setHeight(180);
                $drawing->setCoordinates('A' . $rowOffset);
                $drawings[] = $drawing;
                $rowOffset += 14;
            } catch (\Exception $e) {
                Log::warning("Excel export: failed to embed chart image [{$type}]: " . $e->getMessage());
            }
        }
        return $drawings;
    }

    /**
     * WithHeadings + FromCollection only write plain values — no borders, no
     * banding, no filter, nothing that actually reads as "a table" when
     * opened. Wrap the written range in a real Excel Table object instead.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $headings = $this->headings();
                if (empty($headings)) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                [$startCol, $startRow] = Coordinate::coordinateFromString($this->startCell());
                $startColIndex = Coordinate::columnIndexFromString($startCol);
                $endColIndex = $startColIndex + count($headings) - 1;
                $endRow = $startRow + $this->rows->count();

                $range = $this->startCell() . ':' . Coordinate::stringFromColumnIndex($endColIndex) . $endRow;

                $tableName = 'Tbl_' . preg_replace('/[^A-Za-z0-9_]/', '_', $this->title());
                $tableName = preg_match('/^[A-Za-z_]/', $tableName) ? $tableName : "T{$tableName}";

                $table = new Table($range, $tableName);
                $table->getStyle()->setTheme(TableStyle::TABLE_STYLE_MEDIUM2)->setShowRowStripes(true);

                $sheet->addTable($table);
            },
        ];
    }
}
