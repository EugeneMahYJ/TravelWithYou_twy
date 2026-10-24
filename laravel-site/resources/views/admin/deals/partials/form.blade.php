<form class="admin-editor-form admin-deal-form" method="post" action="{{ $deal->exists ? route('admin.deals.update', $deal) : route('admin.deals.store') }}">
    @csrf
    @if($deal->exists)@method('put')@endif
    <label><span>Name</span><input name="name" value="{{ old('name', $deal->name) }}" required maxlength="160"></label>
    <label><span>Type</span><select name="type" required>@foreach(['New user', 'Limited-time', 'Early bird', 'Group discount'] as $type)<option value="{{ $type }}" @selected(old('type', $deal->type) === $type)>{{ $type }}</option>@endforeach</select></label>
    <label><span>Package</span><select name="package_id" required><option value="">Choose a package</option>@foreach($packages as $package)<option value="{{ $package->id }}" @selected((string) old('package_id', $deal->package_id) === (string) $package->id)>{{ $package->name }}</option>@endforeach</select></label>
    <label><span>Discount (%)</span><input type="number" name="discount_percent" min="1" max="90" step="1" value="{{ old('discount_percent', $deal->discount_percent) }}" required></label>
    <label class="form-wide"><span>Terms</span><textarea name="terms" rows="4" maxlength="2000" required>{{ old('terms', $deal->terms ?? $deal->description) }}</textarea></label>
    <label><span>Ends on</span><input type="datetime-local" name="expires_at" value="{{ old('expires_at', $deal->expires_at?->format('Y-m-d\TH:i') ?? $deal->ends_at?->format('Y-m-d\T23:59')) }}"></label>
    <label class="admin-deal-active"><input type="checkbox" name="active" value="1" @checked(old('active', $deal->exists ? $deal->active : true))><span>Active</span></label>
    <div class="admin-form-actions"><button class="button button-primary" type="submit">Save deal</button><a class="button button-ghost" href="{{ route('admin.deals') }}">Cancel</a></div>
</form>
