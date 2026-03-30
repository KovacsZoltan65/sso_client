<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_user_id')->nullable()->after('id');
            $table->string('local_status')->default('active')->after('email_verified_at');
            $table->text('notes')->nullable()->after('local_status');
            $table->timestamp('last_authenticated_at')->nullable()->after('notes');
            $table->string('password')->nullable()->change();
            $table->unique('sso_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sso_user_id']);
            $table->dropColumn([
                'sso_user_id',
                'local_status',
                'notes',
                'last_authenticated_at',
            ]);
            $table->string('password')->nullable(false)->change();
        });
    }
};
