<?php

namespace App\Http\Requests;

use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAdminPackageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && is_string($this->input('name')) && filled($this->input('name'))) {
            $this->merge(['slug' => $this->route('package')?->slug ?? Str::slug($this->input('name'))]);
        }

        if (! $this->boolean('builder_form')) {
            return;
        }

        $country = is_string($this->input('country'))
            ? Country::query()->where('slug', $this->input('country'))->first()
            : null;
        if ($country) {
            $this->merge([
                'country' => $country->name,
                'region' => $country->region,
                'country_id' => $country->id,
            ]);
        }

        if ($this->input('status') === 'Draft') {
            $this->merge(['published' => false]);
        } else {
            $this->merge(['published' => true]);
        }
    }

    public function rules(): array
    {
        $builder = $this->boolean('builder_form');
        $published = $builder ? $this->input('status') !== 'Draft' : $this->boolean('published', true);

        return [
            'builder_form' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'alpha_dash', 'max:180', Rule::unique('travel_packages', 'slug')->ignore($this->route('package')?->id)],
            'country' => [$published ? 'required' : 'nullable', 'string', 'max:120'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'region' => [$published ? 'required' : 'nullable', 'string', 'max:80'],
            'type' => [$published ? 'required' : 'nullable', 'string', 'max:80'],
            'ship' => ['nullable', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:160'],
            'operator' => ['nullable', 'string', 'max:200'],
            'days' => [$published ? 'required' : 'nullable', 'integer', 'min:1', 'max:365'],
            'nights' => ['nullable', 'integer', 'min:0', 'max:365'],
            'description' => [$published ? 'required' : 'nullable', 'string', 'max:500'],
            'long_description' => [$builder && $published ? 'required' : 'nullable', 'string', 'max:5000'],
            'details' => [$published && ! $builder ? 'required' : 'nullable', 'string', 'max:5000'],
            'price_from' => ['nullable', 'integer', 'min:1'],
            'price_to' => ['nullable', 'integer', 'gte:price_from'],
            'image' => [$published ? 'required' : 'nullable', 'string', 'max:500'],
            'departure_port' => [$published ? 'required' : 'nullable', 'string', 'max:120'],
            'destination_port' => [$published ? 'required' : 'nullable', 'string', 'max:120'],
            'departures_text' => [$builder || ! $published ? 'nullable' : 'required', 'string', 'max:1000'],
            'departures' => [$builder && $published ? 'required' : 'nullable', 'array', 'min:1'],
            'departures.*' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'distinct'],
            'destinations_text' => ['nullable', 'string', 'max:5000'],
            'highlights' => [$builder && $published ? 'required' : 'nullable', 'array', 'min:1'],
            'highlights.*' => ['required', 'string', 'max:240'],
            'included_present' => ['sometimes', 'boolean'],
            'included' => ['nullable', 'array', 'max:50'],
            'included.*' => ['nullable', 'string', 'max:240'],
            'excluded_present' => ['sometimes', 'boolean'],
            'excluded' => ['nullable', 'array', 'max:50'],
            'excluded.*' => ['nullable', 'string', 'max:240'],
            'trip_plan' => [$builder && $published ? 'required' : 'nullable', 'array', 'min:1'],
            'trip_plan.*.place' => [$builder && $published ? 'required' : 'nullable', 'string', 'max:160'],
            'trip_plan.*.activities' => [$builder && $published ? 'required' : 'nullable', 'string', 'max:1000'],
            'trip_plan.*.transport' => ['nullable', 'string', 'max:240'],
            'status' => [$builder ? 'required' : 'nullable', 'in:Published,Expiring,Draft'],
            'source_data_json' => ['nullable', 'json', 'max:200000'],
            'published' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('source_data_json') && ! is_object(json_decode($this->input('source_data_json')))) {
                $validator->errors()->add('source_data_json', 'Brochure data must be a JSON object.');
            }
            if (! $this->boolean('builder_form') && $this->boolean('published', true)) {
                $dates = preg_split('/\r\n|\r|\n/', $this->input('departures_text', ''), -1, PREG_SPLIT_NO_EMPTY);
                $dateValidator = \Illuminate\Support\Facades\Validator::make(
                    ['dates' => $dates],
                    ['dates' => ['required', 'array', 'min:1'], 'dates.*' => ['date_format:Y-m-d', 'after_or_equal:today', 'distinct']],
                );
                foreach ($dateValidator->errors()->all() as $message) {
                    $validator->errors()->add('departures_text', $message);
                }
            }
            if (! $this->boolean('builder_form') || $this->input('status') === 'Draft') {
                return;
            }

            $days = (int) $this->input('days');
            $plan = is_array($this->input('trip_plan')) ? $this->input('trip_plan') : [];
            if ($plan && count($plan) !== $days) {
                $validator->errors()->add('trip_plan', 'The trip plan must have the same number of days as the package duration.');
            }
            if (collect($plan)->contains(fn ($day) => ! is_array($day) || blank($day['place'] ?? null) || blank($day['activities'] ?? null))) {
                $validator->errors()->add('trip_plan', 'Every day needs a place and activities.');
            }
            if (! collect($this->input('highlights', []))->contains(fn ($highlight) => filled($highlight))) {
                $validator->errors()->add('highlights', 'Add at least one highlight.');
            }
            if ($this->input('type') === 'Cruise' && blank($this->input('ship'))) {
                $validator->errors()->add('ship', 'Choose the ship for a cruise.');
            }
            if ((int) $this->input('price_from') <= 0) {
                $validator->errors()->add('price_from', 'Enter a starting price above 0.');
            }
            if ((int) $this->input('price_to') <= 0) {
                $validator->errors()->add('price_to', 'Enter a top price above 0.');
            } elseif ((int) $this->input('price_to') < (int) $this->input('price_from')) {
                $validator->errors()->add('price_to', 'Top price must not be below the starting price.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Add a cover image.',
            'country.required' => 'Choose a country.',
            'type.required' => 'Choose a trip type.',
            'days.required' => 'Enter the number of days (1 or more).',
            'departure_port.required' => 'Enter the departure port or city.',
            'destination_port.required' => 'Enter the destination port or city.',
            'description.required' => 'Enter a short summary.',
            'long_description.required' => 'Enter the full description.',
            'departures.required' => 'Add at least one departure date.',
            'departures.*.distinct' => 'Departure dates must be different.',
            'highlights.required' => 'Add at least one highlight.',
        ];
    }
}
