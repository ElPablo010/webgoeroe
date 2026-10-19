<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: bestaande sites hebben de kolom al.
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'role')) {
            return;
        }

        // Waarden = Webgoeroe\Core\Enums\UserRole (staff/admin). Bewust als string, zodat
        // de migratie niet van een klasse in het project afhangt.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('email');
        });

        // Wie op dit moment al een account heeft, is beheerder; nieuwe
        // gebruikers zijn standaard personeel en moeten gepromoveerd worden.
        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
