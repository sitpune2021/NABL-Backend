<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrefixConfig extends Model
{
    use SoftDeletes;

    public const MASTER_KEYS = [
        'categories' => 'Categories',
        'sub_categories' => 'Sub Categories',
        'departments' => 'Departments',
        'units' => 'Units',
        'templates' => 'Templates',
        'zones' => 'Zones',
        'clusters' => 'Clusters',
        'locations' => 'Locations',
        'instruments' => 'Instruments',
    ];

    protected $fillable = [
        'master_key',
        'master_name',
        'separator',
        'segment_count',
        'value_min_length',
        'value_max_length',
        'characters_min_length',
        'characters_max_length',
        'segments',
        'is_active',
    ];

    protected $casts = [
        'segment_count' => 'integer',
        'value_min_length' => 'integer',
        'value_max_length' => 'integer',
        'characters_min_length' => 'integer',
        'characters_max_length' => 'integer',
        'segments' => 'array',
        'is_active' => 'boolean',
    ];

    public function validateValue(string $value): array
    {
        $errors = [];
        $parts = explode($this->separator, $value);
        $valueLength = strlen($value);
        $charactersLength = strlen(str_replace($this->separator, '', $value));

        if ($valueLength < $this->value_min_length) {
            $errors[] = "Value must be at least {$this->value_min_length} characters including separators.";
        }

        if ($valueLength > $this->value_max_length) {
            $errors[] = "Value may not be greater than {$this->value_max_length} characters including separators.";
        }

        if ($charactersLength < $this->characters_min_length) {
            $errors[] = "Value must have at least {$this->characters_min_length} characters excluding separators.";
        }

        if ($charactersLength > $this->characters_max_length) {
            $errors[] = "Value may not have more than {$this->characters_max_length} characters excluding separators.";
        }

        if (count($parts) !== $this->segment_count) {
            $errors[] = "Value must have exactly {$this->segment_count} parts separated by '{$this->separator}'.";
        }

        foreach ($parts as $index => $part) {
            $rule = $this->segments[$index] ?? null;

            if (!$rule) {
                continue;
            }

            $partNumber = $index + 1;
            $errors = array_merge($errors, $this->validateSegment($part, $partNumber, $rule));
        }

        return $errors;
    }

    private function validateSegment(string $part, int $partNumber, array $rule): array
    {
        $errors = [];
        $length = strlen($part);
        $minLength = (int) ($rule['min_length'] ?? 1);
        $maxLength = (int) ($rule['max_length'] ?? 255);
        $type = $rule['type'] ?? 'any';
        $case = $rule['case'] ?? 'any';
        $startsWith = $rule['starts_with'] ?? 'any';

        if ($length < $minLength) {
            $errors[] = "Part {$partNumber} must be at least {$minLength} characters.";
        }

        if ($length > $maxLength) {
            $errors[] = "Part {$partNumber} may not be greater than {$maxLength} characters.";
        }

        if ($type === 'letters' && !preg_match('/^[A-Za-z]+$/', $part)) {
            $errors[] = "Part {$partNumber} must contain letters only.";
        }

        if ($type === 'numbers' && !preg_match('/^[0-9]+$/', $part)) {
            $errors[] = "Part {$partNumber} must contain numbers only.";
        }

        if ($type === 'alphanumeric' && !preg_match('/^[A-Za-z0-9]+$/', $part)) {
            $errors[] = "Part {$partNumber} must contain letters and numbers only.";
        }

        if ($case === 'upper' && $part !== strtoupper($part)) {
            $errors[] = "Part {$partNumber} must be uppercase.";
        }

        if ($case === 'lower' && $part !== strtolower($part)) {
            $errors[] = "Part {$partNumber} must be lowercase.";
        }

        if ($case === 'title' && $part !== ucfirst(strtolower($part))) {
            $errors[] = "Part {$partNumber} must be title case.";
        }

        if ($startsWith === 'letter' && !preg_match('/^[A-Za-z]/', $part)) {
            $errors[] = "Part {$partNumber} must start with a letter.";
        }

        if ($startsWith === 'number' && !preg_match('/^[0-9]/', $part)) {
            $errors[] = "Part {$partNumber} must start with a number.";
        }

        return $errors;
    }
}
