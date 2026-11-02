<x-layouts.public-auth
    title="Farmer registration"
    description="Create an AgriConnect Farmer Portal account and submit your farmer registration for Municipal Agriculture Office review."
>
    <section class="public-auth-shell public-auth-shell-register" aria-labelledby="register-title">
        <aside class="public-auth-visual">
            <img
                src="{{ asset('images/philippine-rice-field-farmer-960.jpg') }}"
                srcset="{{ asset('images/philippine-rice-field-farmer-960.jpg') }} 960w, {{ asset('images/philippine-rice-field-farmer.jpg') }} 1280w"
                sizes="(max-width: 800px) 100vw, 48vw"
                width="960"
                height="640"
                alt="A farmer standing in a rice field in Bohol, Philippines; an illustrative photograph, not an MAO event photo."
                fetchpriority="high"
            >
            <div class="public-auth-visual-copy">
                <p class="public-auth-visual-eyebrow"><x-ui.icon name="office" size="sm" /> Bongabong Municipal Agriculture Office</p>
                <h2>Simulan ang iyong koneksyon sa serbisyong pang-agrikultura.</h2>
                <p>Create a Farmer Portal account to access services and follow official program updates.</p>
                <div class="public-registration-guidance">
                    <x-ui.icon name="success" size="sm" />
                    <p>After registration, MAO verification may be required. Creating an account does not automatically verify a farmer or approve assistance.</p>
                </div>
                <p class="public-photo-credit">Illustrative rice-field photo by &Oslash;yvind Holmstad, Wikimedia Commons | CC BY-SA 4.0</p>
            </div>
        </aside>

        <div class="public-auth-panel public-auth-panel-register">
            <nav class="public-auth-switch" aria-label="Account access">
                <a href="{{ route('login') }}">Login</a>
                <a href="{{ route('farmer.register') }}" aria-current="page">Register</a>
            </nav>

            <div class="public-auth-heading">
                <p class="public-agri-eyebrow"><x-ui.icon name="users" size="sm" /> Farmer Portal registration</p>
                <h1 id="register-title">Create your account</h1>
                <p>Enter your contact and location information to begin your farmer registration.</p>
            </div>

            @if($errors->any())
                <div id="registration-errors" class="public-form-errors" role="alert" tabindex="-1">
                    <strong>Please review the information below.</strong>
                    <ul>
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form class="public-auth-form public-registration-form" method="POST" action="{{ route('farmer.register.store') }}" data-public-loading>
                @csrf
                <fieldset class="public-form-group">
                    <legend>Personal information</legend>
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
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="30" placeholder="e.g. 09XX XXX XXXX" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone')<p class="public-field-error" id="phone-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="public-form-group">
                    <legend>Location information</legend>
                    <div class="public-form-grid">
                        <div class="public-field">
                            <label for="barangay">Barangay <span>Required</span></label>
                            <input id="barangay" name="barangay" type="text" value="{{ old('barangay') }}" autocomplete="address-level3" maxlength="100" required @error('barangay') aria-invalid="true" aria-describedby="barangay-error" @enderror>
                            @error('barangay')<p class="public-field-error" id="barangay-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field">
                            <label for="address">Address <span>Optional</span></label>
                            <input id="address" name="address" type="text" value="{{ old('address') }}" autocomplete="street-address" maxlength="255" placeholder="Street, sitio, or purok" @error('address') aria-invalid="true" aria-describedby="address-error" @enderror>
                            @error('address')<p class="public-field-error" id="address-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="public-form-group">
                    <legend>Account security</legend>
                    <p class="public-form-hint">Use at least 12 characters. Your password is not shown after you submit this form.</p>
                    <div class="public-form-grid">
                        <div class="public-field">
                            <label for="password">Password <span>Required</span></label>
                            <div class="public-password-input">
                                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                                <button type="button" class="public-password-toggle" aria-controls="password" aria-label="Show password" aria-pressed="false" data-password-toggle>Show password</button>
                            </div>
                            @error('password')<p class="public-field-error" id="password-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="public-field">
                            <label for="password_confirmation">Confirm password <span>Required</span></label>
                            <div class="public-password-input">
                                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror>
                                <button type="button" class="public-password-toggle" aria-controls="password_confirmation" aria-label="Show password" aria-pressed="false" data-password-toggle>Show password</button>
                            </div>
                            @error('password_confirmation')<p class="public-field-error" id="password-confirmation-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <button class="public-button public-button-primary public-auth-submit" type="submit" data-loading-label="Submitting registration...">Create farmer account</button>
                <p class="public-registration-disclaimer">Registration creates a portal account and a farmer record for review. It does not automatically approve assistance applications.</p>
            </form>

            <p class="public-auth-switch-copy">Already registered? <a href="{{ route('login') }}">Sign in to your account</a></p>
            <a class="public-back-home" href="{{ route('home') }}"><x-ui.icon name="back" size="sm" /> Back to homepage</a>
        </div>
    </section>
</x-layouts.public-auth>
