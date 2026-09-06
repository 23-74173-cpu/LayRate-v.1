<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports the aggregated forecast dataset (built from the native production
 * tables) as a formatted .xlsx: a wider date column, a frozen header row, and
 * a colored header row so the sheet is easy to read.
 */
class ProductionDataExport implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    public function __construct(private Collection $records)
    {
    }

    public function collection(): Collection
    {
        return $this->records->map(fn ($r) => [
            $r->date,
            $r->cage_code,
            $r->breed,
            $r->flock_age_weeks,
            $r->hen_count,
            $r->egg_count,
            $r->temperature_c,
            $r->humidity_percent,
            $r->crude_protein_percent,
            $r->feed_consumed_kg,
            $r->mortality_count,
        ]);
    }

    public function headings(): array
    {
        return [
            'Date', 'Cage Code', 'Breed', 'Flock Age (wks)', 'Hen Count',
            'Egg Count', 'Temp (°C)', 'Humidity (%)', 'Crude Protein (%)',
            'Feed (kg)', 'Mortality',
        ];
    }

    public function title(): string
    {
        return 'Production Data';
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $sheet->getHighestColumn();

        return [
            // Colored header row
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF002D5E']],
                'alignment' => ['horizontal' => 'center'],
            ],
            // Alternating row tint + top border for readability
            'A2:' . $lastCol . $sheet->getHighestRow() => [
                'border' => [
                    'outline' => true,
                    'top'     => ['borderStyle' => 'thin', 'color' => ['argb' => 'FFE6E6E6']],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();

                // Wider date column + sensible default widths for the rest.
                $widths = [
                    'A' => 22, // Date — extra width so it never renders as ####
                    'B' => 12,
                    'C' => 18,
                    'D' => 14,
                    'E' => 11,
                    'F' => 11,
                    'G' => 10,
                    'H' => 13,
                    'I' => 15,
                    'J' => 11,
                    'K' => 11,
                ];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth($w);
                }

                // Freeze row 1 so the header stays visible when scrolling.
                $sheet->freezePane('A2');

                // Auto-striped rows for readability.
                $rowCount = $sheet->getHighestRow();
                for ($i = 2; $i <= $rowCount; $i++) {
                    if ($i % 2 === 0) {
                        foreach (range('A', $lastCol) as $col) {
                            $fill = $sheet->getStyle($col . $i)->getFill();
                            $fill->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                            $fill->getStartColor()->setARGB('FFFFF7F8FA');
                        }
                    }
                }
            },
        ];
    }
}
