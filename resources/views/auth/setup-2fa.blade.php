<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Set Up Two-Factor Authentication</title>

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
            max-width: 760px;
        }

        .card {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 15px 45px rgba(15, 23, 42, 0.10);
            overflow: hidden;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            text-align: center;
            padding: 26px 24px 20px;
            border-bottom: 1px solid #eef2f7;
        }

        .icon {
            width: 46px;
            height: 46px;
            margin: 0 auto 12px;

            border-radius: 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eff6ff;
            color: #2563eb;

            font-size: 22px;
        }

        .header h1 {
            margin: 0;

            font-size: 23px;
            font-weight: 700;
            letter-spacing: -0.4px;
        }

        .header p {
            max-width: 520px;

            margin: 8px auto 0;

            color: #64748b;
            line-height: 1.5;
            font-size: 13px;
        }

        /* =========================
           CONTENT
        ========================= */

        .content {
            padding: 22px;
        }

        /* =========================
           STEPS
        ========================= */

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);

            gap: 10px;

            margin-bottom: 20px;
        }

        .step {
            padding: 13px;

            border: 1px solid #e2e8f0;
            border-radius: 12px;

            background: #f8fafc;
        }

        .step-number {
            width: 26px;
            height: 26px;

            border-radius: 50%;

            background: #2563eb;
            color: white;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 12px;
            font-weight: 700;

            margin-bottom: 8px;
        }

        .step strong {
            display: block;

            font-size: 13px;

            margin-bottom: 4px;
        }

        .step span {
            color: #64748b;

            font-size: 11px;
            line-height: 1.4;
        }

        /* =========================
           MAIN SETUP AREA
        ========================= */

        .setup-grid {
            display: grid;

            grid-template-columns: 0.9fr 1.1fr;

            gap: 18px;

            align-items: stretch;
        }

        /* =========================
           QR SECTION
        ========================= */

        .qr-section {
            text-align: center;

            padding: 20px;

            border: 1px solid #e2e8f0;
            border-radius: 14px;

            background: #fafafa;
        }

        .qr-section h2 {
            margin: 0 0 5px;

            font-size: 16px;
        }

        .qr-section p {
            margin: 0 0 14px;

            color: #64748b;

            font-size: 12px;
            line-height: 1.4;
        }

        .qr-code {
            width: 170px;
            height: 170px;

            margin: 0 auto;

            background: white;

            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 8px;

            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.07);
        }

        .qr-code svg,
        .qr-code img {
            max-width: 100%;
            max-height: 100%;
        }

        /* =========================
           APP LINKS
        ========================= */

        .apps {
            margin-top: 15px;
        }

        .apps-title {
            font-size: 11px;
            font-weight: 600;

            color: #475569;

            margin-bottom: 8px;
        }

        .app-links {
            display: flex;

            gap: 7px;

            justify-content: center;

            flex-wrap: wrap;
        }

        .app-link {
            text-decoration: none;

            padding: 7px 9px;

            border-radius: 8px;

            background: white;

            border: 1px solid #e2e8f0;

            color: #334155;

            font-size: 10px;
            font-weight: 600;

            transition: 0.2s;
        }

        .app-link:hover {
            border-color: #2563eb;
            color: #2563eb;
        }

        /* =========================
           VERIFY SECTION
        ========================= */

        .verify-section {
            padding: 20px;

            border: 1px solid #e2e8f0;
            border-radius: 14px;

            background: white;

            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .verify-section h2 {
            margin: 0 0 6px;

            font-size: 16px;
        }

        .verify-section .description {
            color: #64748b;

            font-size: 12px;
            line-height: 1.5;

            margin: 0 0 18px;
        }

        /* =========================
           FORM
        ========================= */

        label {
            display: block;

            font-size: 12px;
            font-weight: 600;

            margin-bottom: 7px;

            color: #334155;
        }

        input {
            width: 100%;

            padding: 12px;

            border: 1px solid #cbd5e1;
            border-radius: 10px;

            outline: none;

            font-size: 18px;
            letter-spacing: 5px;

            text-align: center;

            transition: 0.2s;
        }

        input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        button {
            width: 100%;

            margin-top: 11px;

            padding: 12px;

            border: 0;
            border-radius: 10px;

            background: #2563eb;

            color: white;

            font-size: 13px;
            font-weight: 700;

            cursor: pointer;

            transition: 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        /* =========================
           SECURITY NOTE
        ========================= */

        .security-note {
            margin-top: 13px;

            padding: 10px;

            border-radius: 9px;

            background: #f8fafc;

            color: #64748b;

            font-size: 10px;

            line-height: 1.45;
        }

        /* =========================
           ERRORS
        ========================= */

        .error {
            margin-top: 13px;

            padding: 10px;

            border-radius: 9px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;

            font-size: 11px;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            text-align: center;

            padding: 0 20px 18px;

            color: #94a3b8;

            font-size: 10px;
        }

        /* =========================
           TABLET / PHONE
        ========================= */

        @media (max-width: 650px) {

            body {
                padding: 12px;
                align-items: flex-start;
            }

            .card {
                border-radius: 15px;
            }

            .header {
                padding: 22px 17px 18px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header p {
                font-size: 12px;
            }

            .content {
                padding: 15px;
            }

            .steps {
                grid-template-columns: 1fr;

                gap: 8px;

                margin-bottom: 15px;
            }

            .step {
                padding: 11px;
            }

            .step-number {
                display: inline-flex;

                margin-right: 7px;
                margin-bottom: 0;
            }

            .step strong {
                display: inline;

                font-size: 12px;
            }

            .step span {
                display: block;

                margin-top: 5px;

                font-size: 11px;
            }

            .setup-grid {
                grid-template-columns: 1fr;

                gap: 12px;
            }

            .qr-section,
            .verify-section {
                padding: 17px;
            }

            .qr-code {
                width: 180px;
                height: 180px;
            }

            .footer {
                padding-bottom: 15px;
            }
        }

        /* =========================
           SMALL OPPO PHONES
        ========================= */

        @media (max-width: 400px) {

            .header h1 {
                font-size: 18px;
            }

            .header p {
                font-size: 11px;
            }

            .content {
                padding: 12px;
            }

            .qr-code {
                width: 160px;
                height: 160px;
            }

            input {
                font-size: 17px;
            }

            button {
                font-size: 12px;
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

            <h1>Secure Your Account</h1>

            <p>
                Set up two-factor authentication to protect your account.
                It only takes a minute.
            </p>

        </div>


        <div class="content">

            <!-- STEPS -->
            <div class="steps">

                <div class="step">

                    <div class="step-number">1</div>

                    <strong>Install an authenticator</strong>

                    <span>
                        Use Google Authenticator or Microsoft Authenticator
                        on your phone.
                    </span>

                </div>


                <div class="step">

                    <div class="step-number">2</div>

                    <strong>Scan the QR code</strong>

                    <span>
                        Open the app on your phone and scan the QR code.
                    </span>

                </div>


                <div class="step">

                    <div class="step-number">3</div>

                    <strong>Enter the code</strong>

                    <span>
                        Enter the 6-digit code shown on your phone.
                    </span>

                </div>

            </div>


            <!-- SETUP GRID -->
            <div class="setup-grid">

                <!-- QR CODE -->
                <div class="qr-section">

                    <h2>Scan QR Code</h2>

                    <p>
                        Open your authenticator app and scan this code.
                    </p>


                    <div class="qr-code">
                        {!! $qrCode !!}
                    </div>


                    <div class="apps">

                        <div class="apps-title">
                            Need an authenticator app?
                        </div>


                        <div class="app-links">

                            <a
                                class="app-link"
                                href="https://www.google.com/mobile/authenticator/"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Google Authenticator
                            </a>


                            <a
                                class="app-link"
                                href="https://www.microsoft.com/en-us/security/authenticator/mobile-app"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Microsoft Authenticator
                            </a>

                        </div>

                    </div>

                </div>


                <!-- VERIFY -->
                <div class="verify-section">

                    <h2>Confirm Your Setup</h2>

                    <p class="description">
                        After scanning the QR code, your authenticator app
                        will show a 6-digit code. Enter that code below
                        to activate two-factor authentication.
                    </p>


                    <form
                        method="POST"
                        action="{{ route('2fa.setup.submit') }}"
                    >

                        @csrf


                        <label for="code">
                            6-digit security code
                        </label>


                        <input
                            id="code"
                            type="text"
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            placeholder="000000"
                            required
                        >


                        <button type="submit">
                            Enable Two-Factor Authentication
                        </button>

                    </form>


                    <div class="security-note">
                        🔒 Your security code changes automatically.
                        You can use the code even without an internet connection.
                    </div>


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

            </div>

        </div>


        <div class="footer">
            After setup, you will need a verification code each time you sign in.
        </div>

    </div>

</div>

</body>
</html>

