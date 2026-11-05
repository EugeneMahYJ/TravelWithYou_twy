@extends('layouts.app')
@section('title', 'MICE Group Travel — Travel With You')
@section('description', 'Plan meetings, incentive trips, conferences and company events with Travel With You.')
@section('content')
<x-page-head
    kicker="Meetings · Incentives · Conferences · Events"
    title="Bring your people together."
    lead="Bring your people together somewhere extraordinary. From an inspiring team retreat to a company-wide celebration, we’ll help make the journey as memorable as the reason you gather." />

<section class="wrap mice-intro">
    <div class="mice-intro-copy">
        <p class="eyebrow accent">Work brings you together. Travel makes it matter.</p>
        <h2>Purposeful journeys for teams and organisations.</h2>
        <p>MICE is business travel designed around people and purpose: meetings that move ideas forward, incentive trips that recognise achievement, conferences that spark new thinking, and events worth remembering. We bring the details together so your team can focus on what matters.</p>
    </div>
    <div class="mice-benefits" aria-label="Benefits of MICE travel">
        <article class="mice-benefit-card">
            <img src="{{ asset('assets/boat-and-two-men.jpg') }}" alt="Travellers sharing a boat ride on a mountain lake" loading="lazy">
            <div class="mice-benefit-copy">
                <p class="eyebrow accent">01 · Connect</p>
                <h3>Stronger connections</h3>
                <p>Give colleagues and clients room to connect beyond the everyday.</p>
            </div>
        </article>
        <article class="mice-benefit-card">
            <img src="{{ asset('assets/home/maldive-beach.jpg') }}" alt="A bright tropical island destination" loading="lazy">
            <div class="mice-benefit-copy">
                <p class="eyebrow accent">02 · Recognise</p>
                <h3>Meaningful rewards</h3>
                <p>Celebrate great work with an experience your team will value.</p>
            </div>
        </article>
        <article class="mice-benefit-card">
            <img src="{{ asset('assets/home/danube.jpg') }}" alt="A European city beside the Danube at sunset" loading="lazy">
            <div class="mice-benefit-copy">
                <p class="eyebrow accent">03 · Coordinate</p>
                <h3>One smooth journey</h3>
                <p>Coordinate group travel with one dedicated point of contact.</p>
            </div>
        </article>
        <article class="mice-benefit-card">
            <img src="{{ asset('assets/package-photo-2.jpg') }}" alt="Cherry blossoms in a traditional Japanese garden" loading="lazy">
            <div class="mice-benefit-copy">
                <p class="eyebrow accent">04 · Tailor</p>
                <h3>Made for your goals</h3>
                <p>Shape a programme around your destination, people and budget.</p>
            </div>
        </article>
    </div>
</section>

<section class="mice-enquiry-section" id="mice-enquiry">
    <div class="wrap mice-enquiry-layout">
        <aside class="mice-enquiry-intro">
            <p class="eyebrow accent">Let’s make it happen</p>
            <h2>Tell us about your plans.</h2>
            <p>Share a few details and our team can start putting together the right experience for your group.</p>
            <p class="mice-enquiry-note">No firm plans yet? That’s fine — an estimate of your group size, destination and budget is a great place to start.</p>
        </aside>
        <form class="form-card mice-form" method="post" action="{{ route('mice.submit') }}">
            @csrf
            @if($errors->any())
                <p role="alert" class="notice notice-error">Some details need your attention. Please check the highlighted fields.</p>
            @endif

            <label class="mice-honeypot" aria-hidden="true">Website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>

            <fieldset class="mice-form-section">
                <legend><span>01</span> Company Details</legend>
                <div class="mice-fields">
                    <label class="mice-field mice-field-full" for="mice-company">
                        <span>Company Name</span>
                        <input id="mice-company" name="company" value="{{ old('company') }}" autocomplete="organization" maxlength="255" required>
                        @error('company')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="mice-field" for="mice-travellers">
                        <span>Number of Travellers</span>
                        <input id="mice-travellers" name="travellers" type="number" min="1" max="5000" step="1" inputmode="numeric" value="{{ old('travellers') }}" required>
                        @error('travellers')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="mice-field mice-field-full" for="mice-address">
                        <span>Company Address</span>
                        <textarea id="mice-address" name="company_address" rows="3" maxlength="2000" autocomplete="street-address" required>{{ old('company_address') }}</textarea>
                        @error('company_address')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            </fieldset>

            <fieldset class="mice-form-section">
                <legend><span>02</span> Contact Person</legend>
                <div class="mice-fields">
                    <label class="mice-field" for="mice-honorific">
                        <span>Honorific</span>
                        <select id="mice-honorific" name="honorific" required>
                            <option value="" disabled @selected(old('honorific') === null)>Select one</option>
                            @foreach(['Mr.', 'Mrs.', 'Ms.'] as $honorific)
                                <option value="{{ $honorific }}" @selected(old('honorific') === $honorific)>{{ $honorific }}</option>
                            @endforeach
                        </select>
                        @error('honorific')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="mice-field" for="mice-name">
                        <span>Name</span>
                        <input id="mice-name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required>
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="mice-field" for="mice-mobile">
                        <span>Mobile Number</span>
                        <input id="mice-mobile" name="mobile" type="tel" value="{{ old('mobile') }}" autocomplete="tel" maxlength="32" required>
                        @error('mobile')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="mice-field" for="mice-email">
                        <span>Email Address</span>
                        <input id="mice-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
                        @error('email')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            </fieldset>

            <fieldset class="mice-form-section">
                <legend><span>03</span> Destination</legend>
                <div class="mice-fields">
                    <label class="mice-field mice-field-full" for="mice-destination">
                        <span>Where would you like to go?</span>
                        <input id="mice-destination" name="destination" value="{{ old('destination') }}" maxlength="180" placeholder="e.g. Kyoto, Japan" required>
                        <small class="mice-field-hint">Not sure yet? Let us know if you’re open to destination ideas.</small>
                        @error('destination')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            </fieldset>

            <fieldset class="mice-form-section">
                <legend><span>04</span> Budget</legend>
                <div class="mice-fields">
                    <label class="mice-field mice-field-full" for="mice-budget-basis">
                        <span>This budget range is</span>
                        <select id="mice-budget-basis" name="budget_basis" required>
                            <option value="" disabled @selected(old('budget_basis') === null)>Select one</option>
                            <option value="group" @selected(old('budget_basis') === 'group')>For the whole group</option>
                            <option value="traveller" @selected(old('budget_basis') === 'traveller')>Per traveller</option>
                        </select>
                        @error('budget_basis')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="mice-field" for="mice-budget-from">
                        <span>From (RM)</span>
                        <input id="mice-budget-from" name="budget_from" type="number" min="0" max="9999999999.99" step="0.01" inputmode="decimal" value="{{ old('budget_from') }}" placeholder="5,000" required>
                        @error('budget_from')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="mice-field" for="mice-budget-to">
                        <span>To (RM)</span>
                        <input id="mice-budget-to" name="budget_to" type="number" min="0" max="9999999999.99" step="0.01" inputmode="decimal" value="{{ old('budget_to') }}" placeholder="20,000" required>
                        @error('budget_to')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <p class="mice-budget-help">Enter your estimated budget and select whether it’s for the whole group or per traveller.</p>
            </fieldset>

            <div class="mice-form-footer">
                <p>
                    @auth
                        We’ll reply by email and include the response in your account inbox.
                    @else
                        No account is needed. We’ll reply to your email within one working day.
                    @endauth
                    Your contact details will be used to respond to this enquiry. See our <a href="{{ route('privacy') }}">Privacy Policy</a>.
                </p>
                <button class="button button-primary" type="submit">Send MICE enquiry <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </div>
</section>
@endsection
