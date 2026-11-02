<footer class="public-agri-footer">
    <div class="public-agri-footer-identity">
        <a class="public-agri-footer-brand" href="{{ route('home') }}">AgriConnect</a>
        <p>Official agricultural programs and services from the Bongabong Municipal Agriculture Office.</p>
    </div>
    <nav class="public-agri-footer-links" aria-label="Footer navigation">
        <a href="{{ route('public.agricultural-services.index') }}">Programs and notices</a>
        <a href="{{ route('home') }}#services">Agricultural services</a>
        <a href="{{ route('farmer.register') }}">Farmer registration</a>
        <a href="{{ route('login') }}">Sign in</a>
    </nav>
    <p class="public-agri-copyright">&copy; {{ now()->year }} Bongabong Municipal Agriculture Office</p>
</footer>
