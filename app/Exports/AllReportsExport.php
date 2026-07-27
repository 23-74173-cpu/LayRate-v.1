<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AllReportsExport implements WithMultipleSheets
{
    public function __construct(private array $sections)
    {
    }

    public function sheets(): array
    {
        return collect($this->sections)
            ->map(fn($section) => new ReportSheetExport($section['label'], $section['rows']))
            ->all();
    }
}
