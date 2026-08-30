<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer"><title>Account Recovery</title>
    <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5"><div class="card mx-auto" style="max-width: 480px"><div class="card-body">
@if (session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<h1 class="h4">Choose a New Password</h1>
<form method="POST" action="{{ route('password.update') }}">@csrf
<input type="hidden" id="reset-token" name="token" value="">
<noscript><p>Enable JavaScript to read the secure token from your email link.</p></noscript>
<label for="email" class="form-label">Registered email</label>
<input id="email" type="email" name="email" value="{{ $email }}" class="form-control mb-3" required>
<label for="password" class="form-label">New password (12 to 72 characters)</label>
<input id="password" type="password" name="password" class="form-control mb-3" autocomplete="new-password" minlength="12" maxlength="72" required>
<label for="password_confirmation" class="form-label">Confirm password</label>
<input id="password_confirmation" type="password" name="password_confirmation" class="form-control mb-3" autocomplete="new-password" required>
<button class="btn btn-primary" type="submit">Reset password</button></form>
<p class="mt-3"><a href="{{ route('login') }}">Back to login</a></p>
</div></div></main>
<script>
    // Fragments are never sent in HTTP URLs, keeping bearer tokens out of access logs.
    const token = new URLSearchParams(window.location.hash.slice(1)).get('token') || '';
    document.getElementById('reset-token').value = token;
    history.replaceState(null, '', window.location.pathname + window.location.search);
</script>
</body></html>
