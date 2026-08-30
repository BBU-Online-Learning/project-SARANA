<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Two-Factor Authentication</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            font-family: Inter, Arial, sans-serif;

            background:
                radial-gradient(circle at top left, #dbeafe 0, transparent 32%),
                radial-gradient(circle at bottom right, #e0e7ff 0, transparent 32%),
                #f8fafc;

            color: #0f172a;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px 14px;
        }

        .container {
            width: 100%;
            max-width: 430px;
        }

        .card {
            background: rgba(255, 255, 255, 0.97);

            border: 1px solid #e2e8f0;

            border-radius: 18px;

            box-shadow:
                0 15px 45px rgba(15, 23, 42, 0.10);

            overflow: hidden;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            text-align: center;

            padding: 30px 24px 22px;

            border-bottom: 1px solid #eef2f7;
        }

        .icon {
            width: 52px;
            height: 52px;

            margin: 0 auto 14px;

            border-radius: 15px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eff6ff;

            color: #2563eb;

            font-size: 24px;
        }

        .header h1 {
            margin: 0;

            font-size: 23px;

            font-weight: 700;

            letter-spacing: -0.4px;
        }

        .header p {
            max-width: 340px;

            margin: 9px auto 0;

            color: #64748b;

            font-size: 13px;

            line-height: 1.5;
        }

        /* =========================
           CONTENT
        ========================= */

        .content {
            padding: 24px;
        }

        /* =========================
           INFORMATION
        ========================= */

        .info {
            padding: 14px;

            margin-bottom: 20px;

            border-radius: 12px;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            display: flex;

            gap: 11px;

            align-items: flex-start;
        }

        .info-icon {
            font-size: 18px;

            line-height: 1;
        }

        .info-text {
            color: #475569;

            font-size: 12px;

            line-height: 1.5;
        }

        .info-text strong {
            display: block;

            color: #334155;

            margin-bottom: 3px;
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-top: 5px;
        }

        label {
            display: block;

            margin-bottom: 8px;

            color: #334155;

            font-size: 13px;

            font-weight: 600;
        }

        .code-input {
            width: 100%;

            padding: 14px 12px;

            border: 1px solid #cbd5e1;

            border-radius: 11px;

            outline: none;

            background: white;

            color: #0f172a;

            font-size: 22px;

            font-weight: 600;

            letter-spacing: 7px;

            text-align: center;

            transition: 0.2s;
        }

        .code-input::placeholder {
            color: #cbd5e1;

            letter-spacing: 7px;
        }

        .code-input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        /* =========================
           BUTTON
        ========================= */

        button {
            width: 100%;

            margin-top: 13px;

            padding: 13px;

            border: 0;

            border-radius: 11px;

            background: #2563eb;

            color: white;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.2s;
        }

        button:hover {
            background: #1d4ed8;

            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(0);
        }

        /* =========================
           HELP
        ========================= */

        .help {
            margin-top: 17px;

            text-align: center;

            color: #64748b;

            font-size: 11px;

            line-height: 1.5;
        }

        .help strong {
            color: #475569;
        }

        /* =========================
           ERROR
        ========================= */

        .error {
            margin-top: 15px;

            padding: 11px 12px;

            border-radius: 9px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;

            font-size: 11px;

            line-height: 1.45;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            text-align: center;

            padding: 0 20px 20px;

            color: #94a3b8;

            font-size: 10px;
        }

        /* =========================
           PHONE
        ========================= */

        @media (max-width: 400px) {

            body {
                padding: 12px;
            }

            .card {
                border-radius: 15px;
            }

            .header {
                padding: 24px 18px 18px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header p {
                font-size: 12px;
            }

            .content {
                padding: 17px;
            }

            .code-input {
                font-size: 20px;

                letter-spacing: 6px;
            }

            .code-input::placeholder {
                letter-spacing: 6px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <!-- HEADER -->
        <div class="header">

            <div class="icon">
                🔐
            </div>

            <h1>Two-Factor Verification</h1>

            <p>
                Protecting your account with an additional
                security check.
            </p>

        </div>


        <div class="content">

            <!-- INFORMATION -->
            <div class="info">

                <div class="info-icon">
                    📱
                </div>

                <div class="info-text">

                    <strong>Open your authenticator app</strong>

                    Open Google Authenticator or Microsoft Authenticator
                    on your phone and find the 6-digit code for this account.

                </div>

            </div>


            <!-- FORM -->
            <form
                method="POST"
                action="{{ route('2fa.challenge.submit') }}"
            >

                @csrf


                <div class="form-group">

                    <label for="code">
                        Enter your 6-digit security code
                    </label>


                    <input
                        id="code"
                        class="code-input"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        placeholder="000000"
                        required
                        autofocus
                    >

                </div>


                <button type="submit">
                    Verify &amp; Continue
                </button>

            </form>


            <!-- HELP -->
            <div class="help">

                <strong>Can't find your code?</strong><br>

                Open the authenticator app you used when
                setting up two-factor authentication.

                Your code changes automatically every few seconds.

            </div>


            <!-- ERRORS -->
            @if ($errors->any())

                <div class="error">

                    @foreach ($errors->all() as $error)

                        <div>
                            {{ $error }}
                        </div>

                    @endforeach

                </div>

            @endif

        </div>


        <!-- FOOTER -->
        <div class="footer">

            🔒 Your verification code is private.
            Never share it with anyone.

        </div>

    </div>

</div>

</body>
</html>

