<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();

            if (config('filament-flow.tenant_model')) {
                $tenantTable = (new (config('filament-flow.tenant_model')))->getTable();
                $table->foreignId('tenant_id')->nullable()->constrained($tenantTable)->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('tenant_id')->nullable();
            }

            $table->string('name');
            $table->string('model_type');
            $table->string('state_column')->default('state');
            $table->boolean('is_active')->default(true);
            $table->json('creation_policy')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'model_type', 'state_column'], 'unique_workflow');
        });

        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('label');
            $table->string('class_name')->nullable();

            $table->string('color')->default('gray');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();

            $table->integer('sort_order')->default(999);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'name']);
            $table->index(['workflow_id', 'is_initial']);
            $table->index(['workflow_id', 'is_final']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_state_id')->nullable()->constrained('workflow_states')->cascadeOnDelete();
            $table->foreignId('to_state_id')->nullable()->constrained('workflow_states')->cascadeOnDelete();

            $table->string('name');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('class_name')->nullable();

            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('requires_reason')->default(false);

            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'from_state_id', 'to_state_id', 'name'], 'unique_transition');
            $table->index(['from_state_id', 'to_state_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_states');
        Schema::dropIfExists('workflows');
    }
};
