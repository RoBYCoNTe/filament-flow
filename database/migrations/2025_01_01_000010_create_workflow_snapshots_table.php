<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workflow_snapshots')) {
            return;
        }

        Schema::create('workflow_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('changes')->nullable();
            $table->unsignedInteger('migrated_records')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_snapshots');
    }
};
