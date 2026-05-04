<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claude_sessions', function (Blueprint $table) {
            // First user message text from the JSONL, truncated. Surfaced
            // under unlabeled sessions in the UI so the user can recall what
            // each one was about.
            $table->text('first_user_prompt')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('claude_sessions', function (Blueprint $table) {
            $table->dropColumn('first_user_prompt');
        });
    }
};
