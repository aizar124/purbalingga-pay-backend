<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('reference_code')->nullable()->unique()->after('reference');
        });

        DB::table('transactions')
            ->where('status', 'completed')
            ->update(['status' => 'success']);

        DB::table('transactions')->orderBy('id')->get()->each(function ($transaction) {
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'reference_code' => $transaction->reference_code ?: $transaction->reference,
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_reference_code_unique');
            $table->dropColumn('reference_code');
        });
    }
};
