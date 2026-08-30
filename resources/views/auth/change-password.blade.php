<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    body {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        font-family: system-ui, -apple-system, sans-serif;
    }

    .auth-card {
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        overflow: hidden;
    }

    .auth-header {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        padding: 30px;
        text-align: center;
    }

    .auth-body {
        padding: 30px;
    }

    .avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin: 0 auto 10px;
    }

    .form-control {
        border-radius: 12px;
        padding: 12px;
    }

    .btn-primary {
        border-radius: 12px;
        padding: 12px;
        font-weight: 600;
    }

    .hint {
        font-size: 13px;
        color: #64748b;
    }
</style>

<div class="container d-flex align-items-center justify-content-center min-vh-100">

    <div class="col-12 col-md-6 col-lg-4">

        <div class="auth-card">

            <!-- HEADER -->
            <div class="auth-header">

                <div class="avatar">
                    🔐
                </div>

                <h4 class="fw-bold mb-1">Secure Your Account</h4>

                <div class="small opacity-75">
                    First login detected — update your password
                </div>

            </div>

            <!-- BODY -->
            <div class="auth-body">

                <!-- User Info (from DB concept) -->
                <div class="mb-3 p-3 bg-light rounded-3">
                    <div class="small text-muted">Logged in as</div>
                    <div class="fw-semibold">
                        {{ auth()->user()->name ?? 'User' }}
                    </div>
                    <div class="small text-muted">
                        {{ auth()->user()->email ?? '' }}
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif
                <!-- FORM -->
                <form method="POST" action="{{ route('password.change.submit') }}">
                    @csrf
                    <label for="current-password" class="form-label">Current password</label>
                    <input id="current-password" type="password" name="current_password" class="form-control mb-3" autocomplete="current-password" required>
                    @if (auth()->user()->google2fa_enabled)
                        <label for="verification-code" class="form-label">Fresh authenticator code</label>
                        <input id="verification-code" name="code" inputmode="numeric" pattern="[0-9]{6}" class="form-control mb-2" autocomplete="one-time-code" required>
                        <p class="hint">Wait for a fresh code if you just used one to log in or complete setup.</p>
                    @endif
                    <p><a href="{{ route('password.request') }}">Forgot your password? Log out and request an email reset.</a></p>

                    <!-- NEW PASSWORD -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password"
                               name="password"
                               class="form-control"
                               placeholder="Create strong password"
                               required>
                        <div class="hint mt-1">
                            Use 12 to 72 characters and a different password from your current one
                        </div>
                    </div>

                    <!-- CONFIRM -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <input type="password"
                               name="password_confirmation"
                               class="form-control"
                               placeholder="Repeat password"
                               required>
                    </div>

                    <!-- BUTTON -->
                    <button type="submit" class="btn btn-primary w-100">
                        Update Password
                    </button>

                    <!-- ERRORS -->
                    @if ($errors->any())
                        <div class="alert alert-danger mt-3 small">
                            @foreach ($errors->all() as $error)
                                <div>• {{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                </form>
                @if (auth()->user()->google2fa_enabled && ! auth()->user()->must_change_password)
                    <hr>
                    <h5>Replace Authenticator</h5>
                    <p class="hint">Removing it signs out other sessions and requires a new setup before using the application.</p>
                    <form method="POST" action="{{ route('2fa.disable') }}">
                        @csrf
                        <label class="form-label" for="disable-password">Current password</label>
                        <input id="disable-password" type="password" name="current_password" class="form-control mb-2" autocomplete="current-password" required>
                        <label class="form-label" for="disable-code">Fresh authenticator code</label>
                        <input id="disable-code" name="code" class="form-control mb-2" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" required>
                        <button class="btn btn-outline-danger" type="submit">Verify and replace authenticator</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="btn btn-link" type="submit">Log out</button>
                </form>

            </div>

        </div>

    </div>

</div>
