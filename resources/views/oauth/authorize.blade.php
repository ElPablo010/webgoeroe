<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Toegang verlenen — De Webgoeroe</title>
    <style>
        :root { --brand: #7C3AED; --brand-dark: #6D28D9; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #0f1117; color: #e5e7eb; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Inter, sans-serif;
        }
        .card {
            width: 100%; max-width: 440px; background: #171a23; border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,.4);
        }
        .badge {
            width: 48px; height: 48px; border-radius: 12px; background: var(--brand);
            display: flex; align-items: center; justify-content: center; margin-bottom: 20px;
            font-size: 22px; font-weight: 800; color: #fff;
        }
        h1 { font-size: 20px; margin: 0 0 8px; color: #fff; }
        p { font-size: 14px; line-height: 1.55; color: #9ca3af; margin: 0 0 16px; }
        strong { color: #e5e7eb; }
        .scopes { list-style: none; padding: 0; margin: 0 0 24px; }
        .scopes li {
            display: flex; align-items: center; gap: 10px; font-size: 14px; color: #d1d5db;
            padding: 10px 14px; background: rgba(124,58,237,.08); border: 1px solid rgba(124,58,237,.25);
            border-radius: 10px; margin-bottom: 8px;
        }
        .scopes li::before { content: "✓"; color: var(--brand); font-weight: 800; }
        .who { font-size: 13px; color: #6b7280; margin-bottom: 24px; }
        .actions { display: flex; gap: 12px; }
        button {
            flex: 1; cursor: pointer; border: none; border-radius: 10px; padding: 12px 16px;
            font-size: 15px; font-weight: 600; font-family: inherit;
        }
        .approve { background: var(--brand); color: #fff; }
        .approve:hover { background: var(--brand-dark); }
        .deny { background: transparent; color: #9ca3af; border: 1px solid rgba(255,255,255,.15); }
        .deny:hover { color: #fff; border-color: rgba(255,255,255,.3); }
        form { margin: 0; flex: 1; display: flex; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">W</div>
        <h1>{{ $client->name }} wil toegang</h1>
        <p>
            <strong>{{ $client->name }}</strong> vraagt toestemming om via je account
            <strong>{{ $user->email }}</strong> de blog van De Webgoeroe te beheren.
        </p>

        <ul class="scopes">
            @forelse ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @empty
                <li>De MCP-server gebruiken (blogberichten lezen en schrijven)</li>
            @endforelse
        </ul>

        <p class="who">Verleen deze toegang alleen als jij deze koppeling zelf opzet.</p>

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="approve">Toegang verlenen</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="deny">Weigeren</button>
            </form>
        </div>
    </div>
</body>
</html>
