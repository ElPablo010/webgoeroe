<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class MintMcpToken extends Command
{
    protected $signature = 'mcp:token
        {label=claude : Een herkenbaar label voor het token (bv. de naam van de client)}
        {--email= : E-mailadres van de gebruiker aan wie het token gekoppeld wordt (default: eerste beheerder)}';

    protected $description = 'Genereer een Sanctum bearer-token voor de blog-MCP-server';

    public function handle(): int
    {
        $email = $this->option('email');

        $user = $email
            ? User::where('email', $email)->first()
            : User::where('role', UserRole::Admin)->orderBy('id')->first();

        if (! $user) {
            $this->error($email
                ? "Geen gebruiker gevonden met e-mail {$email}."
                : 'Geen beheerder gevonden om het token aan te koppelen.');

            return self::FAILURE;
        }

        $token = $user->createToken($this->argument('label'))->plainTextToken;

        $this->newLine();
        $this->info("MCP-token aangemaakt voor {$user->email} (label: {$this->argument('label')}).");
        $this->newLine();
        $this->line('  Bewaar dit token nu — het wordt niet opnieuw getoond:');
        $this->newLine();
        $this->line("  <fg=yellow>{$token}</>");
        $this->newLine();
        $this->line('  Gebruik in je MCP-client als header:');
        $this->line('  <fg=gray>Authorization: Bearer '.$token.'</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
