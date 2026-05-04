<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claude_sessions', function (Blueprint $table) {
            // Original working directory captured from the JSONL's user
            // messages. Lets us show "where did this orphan come from?" in
            // the UI without relying on the lossy encoded dir name.
            $table->string('discovered_cwd')->nullable()->after('jsonl_path');
        });
    }

    public function down(): void
    {
        Schema::table('claude_sessions', function (Blueprint $table) {
            $table->dropColumn('discovered_cwd');
        });
    }
};
