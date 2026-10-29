@php
    $selectedPackageIds = collect(old('package_ids', $promoCode->exists ? $promoCode->packages->modelKeys() : []))
        ->map(fn ($id) => (string) $id)
        ->all();
    $allPackages = (string) old('all_packages', $promoCode->all_packages ? '1' : '0') === '1';
@endphp
<form class="admin-editor-form admin-deal-form admin-promo-form" method="post" action="{{ $promoCode->exists ? route('admin.promos.update', $promoCode) : route('admin.promos.store') }}">
    @csrf
    @if($promoCode->exists)@method('put')@endif
    <label class="form-wide"><span>Promo code</span><input name="code" value="{{ old('code', $promoCode->code) }}" required maxlength="40" pattern="[A-Za-z0-9_-]+" autocomplete="off">@error('code')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
    <label><span>Discount type</span>
        <select name="discount_type" data-promo-discount-type required>
            <option value="percent" @selected(old('discount_type', $promoCode->discount_type ?: 'percent') === 'percent')>Percentage</option>
            <option value="fixed" @selected(old('discount_type', $promoCode->discount_type) === 'fixed')>Fixed amount (RM)</option>
        </select>
        @error('discount_type')<small class="field-error" role="alert">{{ $message }}</small>@enderror
    </label>
    <label><span>Discount value <small data-promo-discount-unit>({{ old('discount_type', $promoCode->discount_type) === 'fixed' ? 'RM' : '%' }})</small></span>
        <input type="number" name="discount_value" min="{{ old('discount_type', $promoCode->discount_type) === 'fixed' ? '1' : '0.01' }}" max="{{ old('discount_type', $promoCode->discount_type) === 'fixed' ? '1000000' : '90' }}" step="{{ old('discount_type', $promoCode->discount_type) === 'fixed' ? '1' : '0.01' }}" value="{{ old('discount_value', $promoCode->discount_type === 'fixed' ? $promoCode->discount_amount : $promoCode->discount_percent) }}" data-promo-discount-value required>
        <small class="muted">Enter a percentage (up to 90) or an RM amount, based on the selected type.</small>
        @error('discount_value')<small class="field-error" role="alert">{{ $message }}</small>@enderror
    </label>
    <fieldset class="admin-promo-eligibility form-wide" data-promo-eligibility>
        <legend>Eligible packages</legend>
        <div class="admin-promo-modes">
            <label><input type="radio" name="all_packages" value="1" @checked($allPackages)><span>All packages, including future packages</span></label>
            <label><input type="radio" name="all_packages" value="0" @checked(!$allPackages)><span>Selected packages only</span></label>
            <button class="button button-ghost button-small" type="button" data-promo-select-all>Select all packages</button>
        </div>
        <div class="admin-promo-package-list" data-promo-package-list @if($allPackages) hidden @endif>
            @foreach($packages as $package)
                <label><input type="checkbox" name="package_ids[]" value="{{ $package->id }}" @checked(in_array((string) $package->id, $selectedPackageIds, true))><span>{{ $package->name }} <small>{{ $package->country }}</small></span></label>
            @endforeach
        </div>
        @error('all_packages')<small class="field-error" role="alert">{{ $message }}</small>@enderror
        @error('package_ids')<small class="field-error" role="alert">{{ $message }}</small>@enderror
        @error('package_ids.*')<small class="field-error" role="alert">{{ $message }}</small>@enderror
    </fieldset>
    <label class="form-wide"><span>Terms and conditions</span><textarea name="terms" rows="4" maxlength="2000" required>{{ old('terms', $promoCode->terms) }}</textarea>@error('terms')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
    <label><span>Starts on</span><input type="datetime-local" name="starts_at" value="{{ old('starts_at', $promoCode->starts_at?->format('Y-m-d\TH:i')) }}">@error('starts_at')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
    <label><span>Expires on</span><input type="datetime-local" name="expires_at" value="{{ old('expires_at', $promoCode->expires_at?->format('Y-m-d\TH:i')) }}">@error('expires_at')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
    <label class="admin-deal-active"><input type="checkbox" name="active" value="1" @checked(old('active', $promoCode->exists ? $promoCode->active : true))><span>Active</span></label>
    <div class="admin-form-actions"><button class="button button-primary" type="submit">Save promo</button><a class="button button-ghost" href="{{ route('admin.deals') }}">Cancel</a></div>
</form>
