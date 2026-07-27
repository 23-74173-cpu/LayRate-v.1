<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSheetExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private string $label, private Collection $rows)
    {
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
        // Excel sheet names are capped at 31 characters and can't contain : \ / ? * [ ]
        return Str::limit(preg_replace('/[:\\\\\/?*\[\]]/', '', $this->label), 31, '');
    }
}
