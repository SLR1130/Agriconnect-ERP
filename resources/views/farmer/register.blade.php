<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Farmer Registration - AgriConnect ERP</title>
    <style>body{font-family:system-ui,sans-serif;background:#eaf2e8;margin:0;color:#26352d}.wrap{max-width:760px;margin:40px auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 12px 40px #163d2a1a}h1{color:#163d2a}label{display:block;font-weight:600;margin:14px 0 6px}input,textarea{box-sizing:border-box;width:100%;padding:10px;border:1px solid #cbd5d1;border-radius:8px}button{margin-top:22px;background:#237a52;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-weight:700}.error{color:#b42318;font-size:.9rem}.muted{color:#5b665f}</style>
</head>
<body><main class='wrap'>
    <h1>Farmer Portal Registration</h1>
    <p class='muted'>Create a portal account. Registration remains pending until reviewed by authorized MAO personnel.</p>
    <form method='POST' action='{{ route('farmer.register.store') }}'>
        @csrf
        <label for='name'>Full name</label><input id='name' name='name' value='{{ old('name') }}' required>
        <label for='email'>Email address</label><input id='email' name='email' type='email' value='{{ old('email') }}' required>
        <label for='phone'>Contact number</label><input id='phone' name='phone' value='{{ old('phone') }}'>
        <label for='barangay'>Barangay</label><input id='barangay' name='barangay' value='{{ old('barangay') }}' required>
        <label for='address'>Address</label><textarea id='address' name='address'>{{ old('address') }}</textarea>
        <label for='password'>Password</label><input id='password' name='password' type='password' minlength='12' required>
        <label for='password_confirmation'>Confirm password</label><input id='password_confirmation' name='password_confirmation' type='password' minlength='12' required>
        @if($errors->any())<div class='error'><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <button type='submit'>Create Farmer Account</button>
    </form>
    <p class='muted'>Already registered? <a href='{{ route('login') }}'>Sign in</a></p>
</main></body></html>
