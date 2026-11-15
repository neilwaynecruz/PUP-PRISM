<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

abstract class AbstractTableReportService
{
    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function validateReportFilters(Request $request, array $fields = ['date_from', 'date_to']): array
    {
        $input = [];
        foreach ($fields as $field) {
            $raw = trim((string) $request->input($field, ''));
            $input[$field] = $raw === '' ? null : $raw;
        }

        $rules = [];
        if (in_array('date_from', $fields, true)) {
            $rules['date_from'] = ['nullable', 'date'];
        }
        if (in_array('date_to', $fields, true)) {
            $rules['date_to'] = in_array('date_from', $fields, true)
                ? ['nullable', 'date', 'after_or_equal:date_from']
                : ['nullable', 'date'];
        }
        if (in_array('performed_by', $fields, true)) {
            $rules['performed_by'] = ['nullable', 'integer', 'min:1'];
        }

        $validated = Validator::make($input, $rules)->validate();

        if (isset($validated['date_from'])) {
            $validated['date_from'] = CarbonImmutable::parse((string) $validated['date_from'])->toDateString();
        }
        if (isset($validated['date_to'])) {
            $validated['date_to'] = CarbonImmutable::parse((string) $validated['date_to'])->toDateString();
        }
        if (isset($validated['performed_by'])) {
            $validated['performed_by'] = (int) $validated['performed_by'];
        }

        return $validated;
    }

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
