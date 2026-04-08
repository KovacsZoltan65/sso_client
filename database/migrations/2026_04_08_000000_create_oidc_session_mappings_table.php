<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oidc_session_mappings', function (Blueprint $table): void {
            $table->id();
            $table->char('sid_hash', 64);
            $table->string('session_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('issuer', 2048)->nullable();
            $table->string('client_id')->nullable();
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['sid_hash', 'session_id']);
            $table->index('session_id');
            $table->index(['sid_hash', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oidc_session_mappings');
    }
};
