<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claude_sessions', function (Blueprint $table) {
            // Soft-hide a session from the dashboard (used for orphans the
            // user knows were started by mistake). Re-running cc:discover
            // never clears this -- once dismissed, stays dismissed.
            $table->timestamp('dismissed_at')->nullable()->after('registered');
            $table->index('dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('claude_sessions', function (Blueprint $table) {
            $table->dropIndex(['dismissed_at']);
            $table->dropColumn('dismissed_at');
        });
    }
};
