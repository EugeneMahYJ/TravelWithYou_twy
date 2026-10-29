<?php

namespace App\Http\Requests;

use App\Models\PromoCode;
use App\Models\TravelDeal;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAdminPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        $promoCode = $this->route('promoCode');

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => $this->input('discount_type') === 'fixed'
                ? ['required', 'integer', 'min:1', 'max:1000000']
                : ['required', 'numeric', 'min:0.01', 'max:90'],
            'all_packages' => ['required', 'boolean'],
            'package_ids' => ['required_if:all_packages,0', 'array', 'min:1'],
            'package_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('travel_packages', 'id')->where('published', true),
            ],
            'terms' => ['required', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('code') && $validator->errors()->has('code') === false) {
                $code = mb_strtoupper(trim((string) $this->input('code')));
                $promoCode = $this->route('promoCode');
                $alreadyUsed = PromoCode::query()
                    ->whereRaw('upper(code) = ?', [$code])
                    ->when($promoCode instanceof PromoCode, fn ($query) => $query->where('id', '!=', $promoCode->id))
                    ->exists();
                if ($alreadyUsed) {
                    $validator->errors()->add('code', 'This code has already been taken.');
                }
                $usedByDeal = TravelDeal::query()
                    ->whereRaw('upper(promo_code) = ?', [$code])
                    ->exists();
                if ($usedByDeal) {
                    $validator->errors()->add('code', 'This code is already used by a deal.');
                }
            }

            if ($this->filled('starts_at') && $this->filled('expires_at')
                && $validator->errors()->hasAny(['starts_at', 'expires_at']) === false
                && Carbon::parse($this->input('expires_at'))->lte(Carbon::parse($this->input('starts_at')))) {
                $validator->errors()->add('expires_at', 'The end date must be after the start date.');
            }

            $promoCode = $this->route('promoCode');
            if ($promoCode instanceof PromoCode && $promoCode->expires_at?->isPast()
                && $this->filled('expires_at') && $validator->errors()->has('expires_at') === false
                && Carbon::parse($this->input('expires_at'))->equalTo($promoCode->expires_at)) {
                return;
            }

            if ($this->filled('expires_at') && $validator->errors()->has('expires_at') === false
                && Carbon::parse($this->input('expires_at'))->isPast()) {
                $validator->errors()->add('expires_at', 'The end date must be in the future.');
            }
        });
    }
}
