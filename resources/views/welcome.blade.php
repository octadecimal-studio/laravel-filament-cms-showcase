<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MotoRent Demo — Dev</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; max-width: 480px; padding: 2rem; }
        h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; color: #f59e0b; }
        p { color: #94a3b8; margin-bottom: 2rem; }
        .links { display: flex; flex-direction: column; gap: 1rem; }
        a { display: block; padding: 1rem 2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; font-size: 1.1rem; transition: transform 0.15s, box-shadow 0.15s; }
        a:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .frontend { background: #f59e0b; color: #0f172a; }
        .admin { background: #1e293b; color: #f59e0b; border: 2px solid #f59e0b; }
        .info { margin-top: 2rem; font-size: 0.85rem; color: #64748b; }
        code { background: #1e293b; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>MotoRent Demo</h1>
        <p>Development Environment</p>
        <div class="links">
            <a href="http://localhost:3000" class="frontend">Frontend (Next.js :3000)</a>
            <a href="/admin" class="admin">Admin Panel (Filament)</a>
        </div>
        <div class="info">
            <p>Laravel <code>v{{ Illuminate\Foundation\Application::VERSION }}</code> &middot; PHP <code>v{{ PHP_VERSION }}</code></p>
        </div>
    </div>
</body>
</html>
