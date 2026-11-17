<x-layouts.app>
    @php($editing = $user !== null)
    <x-slot name="header">{{ $editing ? 'Edit account' : 'Create staff account' }}</x-slot>
    <x-slot name="description">{{ $editing ? 'Update profile information without changing the account password.' : 'Create an authorized staff identity with a controlled role and one-time password setup.' }}</x-slot>

    @if($errors->any())
        <div class="erp-alert erp-alert-danger mb-6" role="alert"><div><p class="font-semibold">Please review the form.</p><ul class="mt-1 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_.7fr]">
        <section class="erp-surface">
            <div class="erp-section-header"><div><p class="erp-page-eyebrow">Account identity</p><h2 class="erp-section-title">{{ $editing ? 'Profile information' : 'New authorized identity' }}</h2></div><span class="erp-pill">No plaintext passwords</span></div>
            <form method="POST" action="{{ $editing ? route('erp.users.update', $user) : route('erp.users.store') }}" class="mt-5 space-y-4">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="erp-field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $user?->name) }}" autocomplete="name" required></div>
                <div class="erp-field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" autocomplete="email" required></div>
                @if(!$editing)
                    <div class="erp-field"><label for="role">Initial staff role</label><select id="role" name="role" required><option value="">Select a controlled role</option>@foreach($staffRoles as $slug => $label)<option value="{{ $slug }}" @selected(old('role') === $slug)>{{ $label }}</option>@endforeach</select></div>
                @endif
                <div class="flex flex-wrap gap-2 pt-2"><button class="erp-button erp-button-primary" type="submit">{{ $editing ? 'Save profile changes' : 'Create inactive account' }}</button><a class="erp-button erp-button-secondary" href="{{ $editing ? route('erp.users.show', $user) : route('erp.users.index') }}">Cancel</a></div>
            </form>
        </section>

        <aside class="erp-surface">
            <p class="erp-page-eyebrow">Security workflow</p>
            <h2 class="erp-section-title">{{ $editing ? 'Protected account changes' : 'Password setup is separate' }}</h2>
            @if($editing)
                <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600"><li>Existing passwords are never displayed or overwritten by profile edits.</li><li>Role changes are managed from the account detail page and are limited to approved staff roles.</li><li>Deactivation revokes database-backed sessions and removes access through the active-account middleware.</li></ul>
            @else
                <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600"><li>The new account starts inactive and has a random non-displayable placeholder hash.</li><li>A one-time Laravel password setup link is issued after creation.</li><li>The account becomes active only after the owner sets a password of at least 12 characters.</li></ul>
            @endif
            <div class="mt-5 rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-xs leading-5 text-amber-900">Super Administrator creation is intentionally excluded from this form. Existing Super Administrator identities require separate controlled authorization.</div>
        </aside>
    </div>
</x-layouts.app>
