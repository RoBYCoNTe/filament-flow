<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workflows', 'schema_version')) {
            return;
        }

        Schema::table('workflows', function (Blueprint $table) {
            $table->unsignedInteger('schema_version')->default(1);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workflows', 'schema_version')) {
            return;
        }

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('schema_version');
        });
    }
};
