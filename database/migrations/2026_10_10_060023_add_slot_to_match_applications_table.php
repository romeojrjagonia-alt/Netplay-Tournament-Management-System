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
        Schema::table('match_applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot')->nullable()->after('application_id');
            $table->unique(['match_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_applications', function (Blueprint $table) {
            $table->dropUnique(['match_id', 'slot']);
            $table->dropColumn('slot');
        });
    }
};
