<?php

use App\Mcp\Servers\CmsServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP-servers
|--------------------------------------------------------------------------
|
| De blog-MCP-server is bereikbaar op POST /mcp en accepteert twee auth-wegen:
|
|  1. Sanctum bearer-token — voor Claude Code / desktop.
|     Genereer met:  php artisan mcp:token "<label>"
|     Client-header:  Authorization: Bearer <token>
|
|  2. OAuth 2.1 (Passport) — voor de claude.ai custom connector.
|     Mcp::oauthRoutes() publiceert de metadata- + registratie-endpoints
|     (.well-known/*, /oauth/register); claude.ai registreert zichzelf
|     dynamisch en doorloopt de authorization-code + PKCE flow.
|
| De guard-lijst 'api,sanctum' probeert eerst het OAuth-token, dan het
| Sanctum-token; slaagt één van beide, dan is de request geauthenticeerd.
|
*/

Mcp::oauthRoutes();

Mcp::web('mcp', CmsServer::class)
    ->middleware('auth:sanctum,api')
    ->name('mcp.blog');
