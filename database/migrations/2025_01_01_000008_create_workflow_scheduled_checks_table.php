<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_scheduled_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('state_id')->nullable()->constrained('workflow_states')->cascadeOnDelete();

            $table->enum('condition_type', ['date_offset', 'field_compare', 'custom_class']);
            $table->json('condition_config');

            $table->enum('action_type', ['notification', 'transition', 'side_effect']);
            $table->json('action_config');

            $table->enum('frequency', ['every_minute', 'every_five_minutes', 'hourly', 'daily', 'weekly'])
                ->default('daily');

            $table->boolean('once_per_record')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            $table->index(['workflow_id', 'is_active']);
        });

        Schema::create('workflow_scheduled_check_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained('workflow_scheduled_checks')->cascadeOnDelete();

            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');

            $table->enum('result', ['triggered', 'skipped', 'already_executed', 'error']);
            $table->json('metadata')->nullable();

            $table->timestamp('executed_at')->useCurrent();

            $table->index(['check_id', 'model_type', 'model_id'], 'idx_check_model');
            $table->index('executed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_scheduled_check_logs');
        Schema::dropIfExists('workflow_scheduled_checks');
    }
};
