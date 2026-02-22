<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_files', function (Blueprint $table) {
            $table->string('restoration_status')->default('available')->after('storage_class');
        });
    }

    public function down(): void
    {
        Schema::table('vault_files', function (Blueprint $table) {
            $table->dropColumn('restoration_status');
        });
    }
};