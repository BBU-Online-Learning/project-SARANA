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
<h1 class="h4">Reset Your Password</h1>
<p>Enter your registered email. Password reset does not remove an existing authenticator. If you lost it, contact an authorized institution administrator.</p>
<form method="POST" action="{{ route('password.email') }}">@csrf
<label for="email" class="form-label">Email</label>
<input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control mb-3" autocomplete="email" required>
<button class="btn btn-primary" type="submit">Send reset link</button></form>
<p class="mt-3"><a href="{{ route('login') }}">Back to login</a></p>
</div></div></main>
</body></html>
