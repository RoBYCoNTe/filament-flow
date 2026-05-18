<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_state_transitions', function (Blueprint $table) {
            $table->id();

            $table->string('transitionable_type', 100)->index();
            $table->unsignedBigInteger('transitionable_id')->index();

            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transition_id')->nullable()->constrained('workflow_transitions')->nullOnDelete();

            $table->string('from_state', 150)->nullable();
            $table->string('to_state', 150);
            $table->string('from_state_label', 100)->nullable();
            $table->string('to_state_label', 100);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 150)->nullable();
            $table->string('user_email', 150)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->boolean('has_metadata')->default(false)->index();
            $table->boolean('has_snapshot')->default(false)->index();
            $table->boolean('is_visible')->default(true);

            $table->index(['transitionable_type', 'transitionable_id', 'created_at'], 'idx_transitionable');
            $table->index(['user_id', 'created_at'], 'idx_user_date');
            $table->index(['workflow_id', 'to_state', 'created_at'], 'idx_workflow_state');
            $table->index('created_at');
        });

        Schema::create('workflow_transition_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_history_id')->constrained('workflow_state_transitions')->cascadeOnDelete();

            $table->json('form_data')->nullable();
            $table->json('field_changes')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('rules_evaluated')->nullable();
            $table->json('related_changes')->nullable();
            $table->json('custom_data')->nullable();

            $table->timestamps();

            $table->index('transition_history_id');
        });

        Schema::create('workflow_transition_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_history_id')->constrained('workflow_state_transitions')->cascadeOnDelete();

            $table->enum('snapshot_type', ['before', 'after'])->default('after');

            $table->json('record_data');
            $table->json('related_data')->nullable();

            $table->boolean('is_compressed')->default(false);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['transition_history_id', 'snapshot_type'], 'transition_snapshots_idx');
        });

        Schema::create('workflow_user_involvement', function (Blueprint $table) {
            $table->id();

            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('involvement_type');

            $table->string('state', 150)->nullable();
            $table->timestamp('first_involved_at')->nullable();
            $table->timestamp('last_involved_at')->nullable();
            $table->unsignedInteger('involvement_count')->default(1);

            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'user_id', 'involvement_type', 'state'], 'idx_unique_involvement');
            $table->index(['model_type', 'model_id'], 'idx_model');
            $table->index(['user_id', 'involvement_type'], 'idx_user_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_user_involvement');
        Schema::dropIfExists('workflow_transition_snapshots');
        Schema::dropIfExists('workflow_transition_metadata');
        Schema::dropIfExists('workflow_state_transitions');
    }
};
