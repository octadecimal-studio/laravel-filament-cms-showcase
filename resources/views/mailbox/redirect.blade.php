<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Przekierowanie do Mailbox...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f3f4f6;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 500px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .info-box {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 1rem;
        }
        .email-display {
            background: #f9fafb;
            padding: 0.75rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
            margin: 0.5rem 0;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <p>Przekierowywanie do Mailbox...</p>
        
        <div class="info-box">
            <p><strong>Email do logowania:</strong></p>
            <div class="email-display" id="emailDisplay">{{ $email }}</div>
            <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                Użyj tego samego hasła co w CMS
            </p>
        </div>
    </div>

    <!-- Ukryty formularz do automatycznego logowania w Mailcow -->
    <form id="mailcowLoginForm" method="POST" action="{{ $mailcowUrl }}/api/v1/get/login" target="mailcowFrame" style="display: none;">
        <input type="hidden" name="login_user" value="{{ $email }}">
        <input type="hidden" name="pass_user" id="mailcowPassword" value="">
    </form>
    <iframe id="mailcowFrame" name="mailcowFrame" style="display: none;"></iframe>

    <script>
        const mailcowUrl = '{{ $mailcowUrl }}';
        const mailboxUrl = '{{ $mailboxUrl }}';
        const email = '{{ $email }}';
        
        // Skopiuj email do schowka
        if (navigator.clipboard) {
            navigator.clipboard.writeText(email).then(() => {
                console.log('Email skopiowany do schowka');
            });
        }
        
        // Otwórz Mailcow w nowej karcie
        const newWindow = window.open(mailcowUrl, '_blank');
        
        // Próba automatycznego wypełnienia formularza logowania w Mailcow
        // Uwaga: Ze względu na cross-origin restrictions, może nie działać
        // W takim przypadku użytkownik będzie musiał wprowadzić email ręcznie (ale jest w schowku)
        setTimeout(() => {
            if (newWindow && !newWindow.closed) {
                try {
                    // Mailcow używa formularza z polami: login_user i pass_user
                    const loginForm = newWindow.document.querySelector('form[action*="login"], form[method="post"]');
                    if (loginForm) {
                        const emailInput = loginForm.querySelector('input[name="login_user"], input[name="loginUser"], input[type="text"], input[type="email"]');
                        if (emailInput) {
                            emailInput.value = email;
                            emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                            emailInput.dispatchEvent(new Event('change', { bubbles: true }));
                            
                            // Skoncentruj się na polu hasła
                            const passwordInput = loginForm.querySelector('input[name="pass_user"], input[name="passUser"], input[type="password"]');
                            if (passwordInput) {
                                passwordInput.focus();
                            }
                        }
                    }
                } catch (e) {
                    // Cross-origin error - nie możemy manipulować zawartością Mailcow
                    console.log('Nie można automatycznie wypełnić formularza (cross-origin). Email został skopiowany do schowka - użyj Ctrl+V aby wkleić.');
                }
            }
        }, 2000);
        
        // Przekieruj bieżące okno do panelu admin po 3 sekundach
        setTimeout(() => {
            window.location.href = '/admin';
        }, 3000);
    </script>
</body>
</html>
