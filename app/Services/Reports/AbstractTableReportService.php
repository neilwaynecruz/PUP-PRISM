<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;

abstract class AbstractTableReportService
{
    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, string>
     */
    protected function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$label] = $value;
        }

        return $normalized === [] ? ['Filters' => 'None'] : $normalized;
    }

    protected function generatedBy(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return 'System';
        }

        return trim(sprintf('%s (%s)', $user->name, $user->email));
    }

    protected function formatPosition(?string $title, ?string $department): string
    {
        if ($title === null || $title === '') {
            return '';
        }

        if ($department === null || $department === '') {
            return $title;
        }

        return "{$title}, {$department}";
    }
}
