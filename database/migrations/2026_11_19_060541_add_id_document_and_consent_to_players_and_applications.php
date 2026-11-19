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
        Schema::table('players', function (Blueprint $table) {
            $table->string('id_document_type')->nullable();
            $table->string('id_document_other')->nullable();
            $table->string('id_document_path')->nullable();
            $table->timestamp('data_consent_at')->nullable();
        });

        Schema::table('player_applications', function (Blueprint $table) {
            $table->timestamp('data_consent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['id_document_type', 'id_document_other', 'id_document_path', 'data_consent_at']);
        });

        Schema::table('player_applications', function (Blueprint $table) {
            $table->dropColumn('data_consent_at');
        });
    }
};
