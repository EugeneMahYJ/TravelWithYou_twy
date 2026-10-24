<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAdminDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['New user', 'Limited-time', 'Early bird', 'Group discount'])],
            'package_id' => ['required', 'integer', 'exists:travel_packages,id'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:90'],
            'terms' => ['required', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('expires_at') === false || $validator->errors()->has('expires_at')) {
                return;
            }

            $end = Carbon::parse($this->input('expires_at'));
            if ($end->gte(now())) {
                return;
            }

            $deal = $this->route('deal');
            $unchangedPastEnd = (bool) ($deal?->expires_at
                ? $end->format('Y-m-d H:i') === $deal->expires_at->format('Y-m-d H:i')
                : ($deal?->ends_at && $end->toDateString() === $deal->ends_at->toDateString()));
            if ($unchangedPastEnd === false) {
                $validator->errors()->add('expires_at', 'The end date must be in the future.');
            }
        });
    }
}
