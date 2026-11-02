<header class="public-agri-header">
    <a class="public-agri-brand" href="{{ route('home') }}" aria-label="AgriConnect home">
        <span class="public-agri-mark" aria-hidden="true"><x-ui.icon name="crop" size="lg" /></span>
        <span><strong>AgriConnect</strong><small>Bongabong Municipal Agriculture Office</small></span>
    </a>

    <button class="public-agri-nav-toggle" type="button" aria-controls="public-primary-navigation" aria-expanded="false" aria-label="Open navigation" data-public-nav-toggle>
        <x-ui.icon name="menu" />
        <span>Menu</span>
    </button>

    <nav id="public-primary-navigation" class="public-agri-nav" aria-label="Main navigation" data-public-navigation>
        <a href="{{ route('public.agricultural-services.index') }}" @if(request()->routeIs('public.agricultural-services.*', 'public.agricultural-programs.*', 'public.announcements.*')) aria-current="page" @endif>Programs &amp; notices</a>
        <a href="{{ route('home') }}#services">Agricultural services</a>
        <a href="{{ route('home') }}#how-it-works">How it works</a>
        @auth
            @if(auth()->user()->hasRole('farmer'))
                <a class="public-agri-nav-cta" href="{{ route('farmer.portal') }}">My Farmer Portal</a>
            @else
                <a class="public-agri-nav-cta" href="{{ route('dashboard') }}">Open workspace</a>
            @endif
        @else
            <a href="{{ route('farmer.register') }}" @if(request()->routeIs('farmer.register')) aria-current="page" @endif>Farmer registration</a>
            <a class="public-agri-nav-signin" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Sign in</a>
        @endauth
    </nav>
</header>
