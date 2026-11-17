<x-layouts.public-auth
    title="Sign in to the Farmer Portal"
    description="Sign in to AgriConnect, the farmer services portal of Bongabong Municipal Agriculture Office."
>
    <section class="public-auth-shell" aria-labelledby="login-title">
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
                <h2>Serbisyong pang-agrikultura, mas madaling maabot.</h2>
                <p>Access your farmer services, review program requirements, and follow your applications and official updates.</p>
                <p class="public-photo-credit">Illustrative rice-field photo by &Oslash;yvind Holmstad, Wikimedia Commons | CC BY-SA 4.0</p>
            </div>
        </aside>

        <div class="public-auth-panel">
            <nav class="public-auth-switch" aria-label="Account access">
                <a href="{{ route('login') }}" aria-current="page">Login</a>
                <a href="{{ route('farmer.register') }}">Register</a>
            </nav>

            <div class="public-auth-heading">
                <p class="public-agri-eyebrow"><x-ui.icon name="security" size="sm" /> Farmer Portal</p>
                <h1 id="login-title">Welcome back</h1>
                <p>Sign in to continue to your AgriConnect services.</p>
            </div>

            @if($errors->any())
                <div id="login-errors" class="public-form-errors" role="alert" tabindex="-1">
                    <strong>We could not sign you in.</strong>
                    <ul>
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form class="public-auth-form" method="POST" action="{{ route('login.store') }}" data-public-loading>
                @csrf
                <div class="public-field">
                    <label for="email">Email address <span>Required</span></label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        inputmode="email"
                        autocapitalize="none"
                        required
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    >
                    @error('email')<p class="public-field-error" id="email-error">{{ $message }}</p>@enderror
                </div>

                <div class="public-field">
                    <label for="password">Password <span>Required</span></label>
                    <div class="public-password-input">
                        <input id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button type="button" class="public-password-toggle" aria-controls="password" aria-label="Show password" aria-pressed="false" data-password-toggle>Show password</button>
                    </div>
                    @error('password')<p class="public-field-error" id="password-error">{{ $message }}</p>@enderror
                </div>

                <label class="public-checkbox-field" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                    <span>Remember me on this device</span>
                </label>

                <button class="public-button public-button-primary public-auth-submit" type="submit" data-loading-label="Signing in...">Sign in securely</button>
            </form>

            <p class="public-auth-switch-copy">New to AgriConnect? <a href="{{ route('farmer.register') }}">Create a farmer account</a></p>
            <a class="public-back-home" href="{{ route('home') }}"><x-ui.icon name="back" size="sm" /> Back to homepage</a>
        </div>
    </section>
</x-layouts.public-auth>
