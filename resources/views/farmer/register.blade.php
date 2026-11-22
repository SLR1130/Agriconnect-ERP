@php
    $registrationSteps = ['Your details', 'Farm location', 'Password'];
    $registrationStep = $errors->hasAny(['name', 'email', 'phone', 'address', 'barangay'])
        ? 1
        : ($errors->hasAny(['farm_name', 'farm_barangay', 'farm_location', 'latitude', 'longitude', 'location_source', 'location_accuracy_meters', 'boundary_coordinates'])
            ? 2
            : ($errors->hasAny(['password', 'password_confirmation']) ? 3 : 1));
    $registrationMapFieldValue = fn (string $key, mixed $fallback = null) => old($key, $fallback);
@endphp

<x-layouts.public-auth
    title="Farmer registration"
    description="Create an AgriConnect Farmer Portal account and submit your farmer registration for Municipal Agriculture Office review."
>
    <section class="public-auth-shell public-auth-shell-register" aria-labelledby="register-title">
        <aside class="public-auth-visual public-register-visual" aria-label="Bongabong farmer services">
            <img
                src="{{ asset('images/philippine-rice-field-farmer-960.jpg') }}"
                srcset="{{ asset('images/philippine-rice-field-farmer-960.jpg') }} 960w, {{ asset('images/philippine-rice-field-farmer.jpg') }} 1280w"
                sizes="(max-width: 800px) 100vw, 36vw"
                width="960"
                height="640"
                alt="A farmer standing in a rice field in Bohol, Philippines; an illustrative photograph, not an MAO event photo."
                fetchpriority="high"
            >
            <div class="public-auth-visual-copy">
                <p class="public-auth-visual-eyebrow"><x-ui.icon name="office" size="sm" /> Bongabong Municipal Agriculture Office</p>
                <h2>Start with your farmer account.</h2>
                <p>Register your details, choose your farm's barangay, and pin the location before you submit.</p>

                <div class="public-registration-journey">
                    <p class="public-registration-journey-title">What happens next</p>
                    <ol>
                        <li class="is-current">
                            <span class="public-registration-journey-icon"><x-ui.icon name="users" size="sm" /></span>
                            <span><strong>Your details</strong><small>Tell the MAO how to identify and contact you.</small></span>
                        </li>
                        <li>
                            <span class="public-registration-journey-icon"><x-ui.icon name="location" size="sm" /></span>
                            <span><strong>Pin your first farm</strong><small>Select its barangay and choose its point on the Bongabong map.</small></span>
                        </li>
                    </ol>
                </div>

                <div class="public-registration-guidance">
                    <x-ui.icon name="success" size="sm" />
                    <p>Your account and first farm go to the MAO for review. A map pin is farmer-provided until the MAO verifies it.</p>
                </div>
                <p class="public-photo-credit">Illustrative rice-field photo by &Oslash;yvind Holmstad, Wikimedia Commons | CC BY-SA 4.0</p>
            </div>
        </aside>

        <div class="public-auth-panel public-auth-panel-register">
            <div class="public-registration-topline">
                <span><x-ui.icon name="profile" size="sm" /> Farmer account setup</span>
                <a href="{{ route('login') }}">Already registered? Sign in</a>
            </div>

            <header class="public-auth-heading">
                <h1 id="register-title">Create your farmer account</h1>
                <p>Complete the steps, then submit your first farm with its location pin.</p>
            </header>

            <ol class="public-registration-progress" aria-label="Registration progress" data-registration-progress>
                @foreach($registrationSteps as $index => $label)
                    @php($stepNumber = $index + 1)
                    <li data-registration-indicator="{{ $stepNumber }}" data-state="{{ $stepNumber < $registrationStep ? 'complete' : ($stepNumber === $registrationStep ? 'current' : 'upcoming') }}" @if($stepNumber === $registrationStep) aria-current="step" @endif>
                        <span class="public-progress-marker"><span class="public-progress-number">{{ $stepNumber }}</span><x-ui.icon name="success" size="xs" /></span>
                        <span>{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="public-registration-step-status" data-registration-status aria-live="polite">Step {{ $registrationStep }} of 3: {{ $registrationSteps[$registrationStep - 1] }}</p>

            @if($errors->any())
                <div id="registration-errors" class="public-form-errors" role="alert" tabindex="-1">
                    <strong>Please correct the marked information.</strong>
                    <ul>
                        @foreach($errors->getBag('default')->messages() as $field => $messages)
                            @foreach($messages as $message)
                                @php($errorTarget = in_array($field, ['farm_location', 'boundary_coordinates'], true) ? 'farm-map' : $field)
                                <li><a href="#{{ $errorTarget }}">{{ $message }}</a></li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                class="public-auth-form public-registration-form"
                method="POST"
                action="{{ route('farmer.register.store') }}"
                data-registration-wizard
                data-initial-step="{{ $registrationStep }}"
                data-public-loading
            >
                @csrf
                <noscript><p class="public-form-hint">JavaScript is off, so all registration steps are shown below. Complete the form and submit it at the end.</p></noscript>

                <section class="public-registration-panel" data-registration-panel="1" aria-labelledby="registration-step-1">
                    <div class="public-registration-panel-heading">
                        <span class="public-registration-panel-icon"><x-ui.icon name="users" /></span>
                        <div><h2 id="registration-step-1" tabindex="-1" data-registration-heading>Your details</h2><p>Use the name and email address you want the MAO to contact.</p></div>
                    </div>
                    <div class="public-form-grid">
                        <div class="public-field public-field-wide">
                            <label for="name">Full name <span>Required</span></label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name')<p class="public-field-error" id="name-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field">
                            <label for="email">Email address <span>Required</span></label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" autocapitalize="none" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')<p class="public-field-error" id="email-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field">
                            <label for="phone">Contact number <span>Optional</span></label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="30" placeholder="09XX XXX XXXX" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone')<p class="public-field-error" id="phone-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field public-field-wide">
                            <label for="barangay">Your barangay <span>Required</span></label>
                            <select id="barangay" name="barangay" autocomplete="address-level3" required @error('barangay') aria-invalid="true" aria-describedby="barangay-error" @enderror>
                                <option value="">Choose your barangay</option>
                                @foreach($barangays as $barangay)
                                    <option value="{{ $barangay }}" @selected(old('barangay') === $barangay)>{{ $barangay }}</option>
                                @endforeach
                            </select>
                            @error('barangay')<p class="public-field-error" id="barangay-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field public-field-wide">
                            <label for="address">Your street, sitio, or purok <span>Optional</span></label>
                            <input id="address" name="address" type="text" value="{{ old('address') }}" autocomplete="street-address" maxlength="255" placeholder="Add a street or local landmark" @error('address') aria-invalid="true" aria-describedby="address-error" @enderror>
                            @error('address')<p class="public-field-error" id="address-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="public-registration-panel" data-registration-panel="2" aria-labelledby="registration-step-2">
                    <div class="public-registration-panel-heading">
                        <span class="public-registration-panel-icon"><x-ui.icon name="location" /></span>
                        <div><h2 id="registration-step-2" tabindex="-1" data-registration-heading>Pin your first farm</h2><p>Choose the farm's barangay, then click its actual location on the map.</p></div>
                    </div>
                    <div class="public-form-grid">
                        <div class="public-field public-field-wide">
                            <label for="farm_name">Farm name <span>Required</span></label>
                            <input id="farm_name" name="farm_name" type="text" value="{{ old('farm_name') }}" autocomplete="organization" maxlength="255" placeholder="Name for your farm" required @error('farm_name') aria-invalid="true" aria-describedby="farm-name-error" @enderror>
                            @error('farm_name')<p class="public-field-error" id="farm-name-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field public-field-wide">
                            <label for="farm_barangay">Farm barangay <span>Required</span></label>
                            <select id="farm_barangay" name="farm_barangay" data-farm-location-barangay required aria-describedby="farm-barangay-hint @error('farm_barangay') farm-barangay-error @enderror" @error('farm_barangay') aria-invalid="true" @enderror>
                                <option value="">Choose the farm's barangay</option>
                                @foreach($barangays as $barangay)
                                    <option value="{{ $barangay }}" @selected(old('farm_barangay', old('barangay')) === $barangay)>{{ $barangay }}</option>
                                @endforeach
                            </select>
                            <small class="public-form-hint" id="farm-barangay-hint">Your farm can be in a different barangay from your home.</small>
                            @error('farm_barangay')<p class="public-field-error" id="farm-barangay-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field public-field-wide">
                            <label for="farm_location_details">Farm sitio or landmark <span>Optional</span></label>
                            <input id="farm_location_details" name="farm_location_details" data-farm-location-details type="text" value="{{ old('farm_location_details') }}" maxlength="255" placeholder="Public sitio, purok, road, or landmark" @error('farm_location_details') aria-invalid="true" aria-describedby="farm-location-details-error" @enderror>
                            @error('farm_location_details')<p class="public-field-error" id="farm-location-details-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @include('farmer.partials.farm-location-map', [
                        'prefix' => 'registration-farm',
                        'mapId' => 'farm-map',
                        'fieldsetId' => 'farm_location',
                        'requiredPin' => true,
                        'fieldValue' => $registrationMapFieldValue,
                        'showFarmErrors' => true,
                    ])
                </section>

                <section class="public-registration-panel" data-registration-panel="3" aria-labelledby="registration-step-3">
                    <div class="public-registration-panel-heading">
                        <span class="public-registration-panel-icon"><x-ui.icon name="security" /></span>
                        <div><h2 id="registration-step-3" tabindex="-1" data-registration-heading>Choose a password</h2><p>Use at least 12 characters. You can paste a password or use a password manager.</p></div>
                    </div>
                    <div class="public-form-grid">
                        <div class="public-field public-field-wide">
                            <label for="password">Password <span>Required</span></label>
                            <div class="public-password-input">
                                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="public-password-toggle" aria-controls="password" aria-label="Show password" aria-pressed="false" data-password-toggle>Show</button>
                            </div>
                            <small class="public-form-hint" id="password-hint">At least 12 characters.</small>
                            @error('password')<p class="public-field-error" id="password-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field public-field-wide">
                            <label for="password_confirmation">Confirm password <span>Required</span></label>
                            <div class="public-password-input">
                                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>
                                <button type="button" class="public-password-toggle" aria-controls="password_confirmation" aria-label="Show password" aria-pressed="false" data-password-toggle>Show</button>
                            </div>
                            @error('password_confirmation')<p class="public-field-error" id="password-confirmation-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="public-registration-disclaimer">Your farmer record starts as pending MAO review. Your farm pin is a location reference, not a verified land survey.</p>
                </section>

                <div class="public-registration-actions">
                    <button class="public-button public-button-outline" type="button" data-registration-back hidden><x-ui.icon name="back" size="sm" /> Back</button>
                    <span class="public-registration-action-spacer"></span>
                    <button class="public-button public-button-primary" type="button" data-registration-next hidden>Continue</button>
                    <button class="public-button public-button-primary public-auth-submit" type="submit" data-registration-submit data-loading-label="Creating your account…"><x-ui.icon name="success" size="sm" /> Create farmer account</button>
                </div>
            </form>

            <a class="public-back-home" href="{{ route('home') }}"><x-ui.icon name="back" size="sm" /> Back to homepage</a>
        </div>
    </section>
</x-layouts.public-auth>
