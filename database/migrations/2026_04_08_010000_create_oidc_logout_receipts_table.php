<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oidc_logout_receipts', function (Blueprint $table): void {
            $table->id();
            $table->char('jti_hash', 64)->unique();
            $table->string('issuer', 2048)->nullable();
            $table->string('audience')->nullable();
            $table->char('sid_hash', 64)->nullable()->index();
            $table->string('outcome')->default('processed');
            $table->timestamp('processed_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oidc_logout_receipts');
    }
};
