<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('player_applications', function (Blueprint $table) {
            $table->string('reference_code', 20)->nullable()->unique()->after('id');
            $table->text('rejection_reason')->nullable()->after('status');
        });

        DB::table('player_applications')->whereNull('reference_code')->orderBy('id')->each(function (object $application): void {
            DB::table('player_applications')
                ->where('id', $application->id)
                ->update(['reference_code' => sprintf('NP-%06d', $application->id)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_applications', function (Blueprint $table) {
            $table->dropUnique(['reference_code']);
            $table->dropColumn(['reference_code', 'rejection_reason']);
        });
    }
};
