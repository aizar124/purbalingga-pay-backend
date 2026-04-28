<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('Warga')->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->unsignedBigInteger('balance')->default(0)->after('password');
            $table->string('status')->default('active')->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'balance', 'status']);
        });
    }
};
