<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AllReportsExport implements WithMultipleSheets
{
    public function __construct(
        private array $sections,
        private array $tempFiles = []
    ) {
    }

    public function sheets(): array
    {
        return collect($this->sections)
            ->map(fn($section) => new ReportSheetExport(
                $section['label'],
                $section['rows'],
                isset($this->tempFiles[$section['type']])
                    ? [$section['type'] => $this->tempFiles[$section['type']]]
                    : []
            ))
            ->all();
    }
}
