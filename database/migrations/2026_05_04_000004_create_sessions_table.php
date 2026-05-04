<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claude_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('guid');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->enum('status', ['active', 'paused', 'done'])->default('active');
            $table->string('jsonl_path')->nullable();
            $table->unsignedBigInteger('jsonl_size_bytes')->nullable();
            $table->timestamp('jsonl_mtime')->nullable();
            $table->boolean('registered')->default(false);
            $table->timestamps();

            $table->unique(['guid', 'account_id']);
            $table->index('jsonl_mtime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_sessions');
    }
};
