<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Unique check that treats "Office Supplies" and "office supplies" as the same value.
 *
 * PostgreSQL compares strings case-sensitively, so the built-in unique rule would
 * allow near-duplicate master data records.
 */
class UniqueIgnoringCase implements ValidationRule
{
    private int|string|null $ignoreId = null;

    /**
     * @var array<string, mixed>
     */
    private array $scope = [];

    public function __construct(
        private readonly string $table,
        private readonly string $column,
    ) {}

    public function ignore(Model|int|string|null $id): static
    {
        $this->ignoreId = $id instanceof Model ? $id->getKey() : $id;

        return $this;
    }

    /**
     * Limit the check to rows matching these column values (e.g. titles within one department).
     */
    public function where(string $column, mixed $value): static
    {
        $this->scope[$column] = $value;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $exists = DB::table($this->table)
            ->whereRaw("LOWER({$this->column}) = ?", [mb_strtolower(trim($value))])
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->where($this->scope)
            ->exists();

        if ($exists) {
            $fail(__('A record with this :attribute already exists.', ['attribute' => str_replace('_', ' ', $attribute)]));
        }
    }
}
