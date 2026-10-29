<?php

namespace App\Http\Requests;

use App\Models\Ship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveAdminShipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && filled($this->input('name'))) {
            $ship = $this->route('ship');
            $this->merge([
                'slug' => $ship?->slug ?? $this->uniqueSlug(Str::slug((string) $this->input('name'))),
            ]);
        } elseif (filled($this->input('slug'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        }

        $this->merge([
            'gallery' => $this->cleanList('gallery'),
            'amenities' => $this->cleanList('amenities'),
            'decks' => $this->cleanRows('decks', ['n', 'label', 'items']),
            'cabins' => $this->cleanRows('cabins', ['type', 'size', 'note']),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:160', Rule::unique('ships', 'slug')->ignore($this->route('ship')?->id)],
            'class_name' => ['required', 'string', 'max:80'],
            'image' => ['required', 'string', 'max:500'],
            'overview' => ['required', 'string', 'max:3000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'crew' => ['required', 'integer', 'min:1', 'max:5000'],
            'rating' => ['required', 'numeric', 'min:0', 'max:5'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(now()->year + 5)],
            'gallery' => ['nullable', 'array', 'max:20'],
            'gallery.*' => ['nullable', 'string', 'max:500'],
            'amenities' => ['nullable', 'array', 'max:60'],
            'amenities.*' => ['nullable', 'string', 'max:160'],
            'decks' => ['nullable', 'array', 'max:30'],
            'decks.*.n' => ['nullable', 'string', 'max:40'],
            'decks.*.label' => ['required', 'string', 'max:120'],
            'decks.*.items' => ['required', 'string', 'max:500'],
            'cabins' => ['nullable', 'array', 'max:30'],
            'cabins.*.type' => ['required', 'string', 'max:80', 'distinct'],
            'cabins.*.size' => ['nullable', 'string', 'max:40'],
            'cabins.*.note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Add a cover image.',
            'overview.required' => 'Write a ship overview.',
            'decks.*.label' => 'Every deck needs a name.',
            'decks.*.items' => 'Describe what is on each deck.',
            'cabins.*.type' => 'Every cabin needs a type.',
            'cabins.*.type.distinct' => 'Each cabin type can only be used once.',
        ];
    }

    /**
     * Trim and drop blank entries from a list of single-value rows.
     *
     * @return array<int, string>
     */
    private function cleanList(string $key): array
    {
        $values = $this->input($key);

        if (! is_array($values)) {
            return [];
        }

        $clean = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    /**
     * Trim a list of rows and drop rows where every field is blank.
     *
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function cleanRows(string $key, array $fields): array
    {
        $rows = $this->input($key);

        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = [];
            $filled = false;
            foreach ($fields as $field) {
                $value = $row[$field] ?? null;
                $value = is_string($value) ? trim($value) : $value;
                $normalized[$field] = $value;
                $filled = $filled || filled($value);
            }

            if ($filled) {
                $clean[] = $normalized;
            }
        }

        return $clean;
    }

    private function uniqueSlug(string $base): string
    {
        $base = $base ?: 'ship';
        $slug = $base;
        $suffix = 2;

        while (Ship::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
