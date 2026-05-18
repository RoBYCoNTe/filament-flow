<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_state_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('workflow_states')->cascadeOnDelete();

            $table->string('field_name');
            $table->enum('visibility', ['visible', 'hidden'])->default('visible');
            $table->enum('mutability', ['readonly', 'editable', 'locked'])->default('editable');
            $table->boolean('is_required')->default(false);

            $table->integer('sort_order')->default(0);
            $table->json('validation_rules')->nullable();

            $table->timestamps();

            $table->unique(['state_id', 'field_name']);
            $table->index('state_id');
        });

        Schema::create('workflow_state_field_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_field_id')->constrained('workflow_state_fields')->cascadeOnDelete();

            $table->string('role_name');
            $table->enum('visibility', ['visible', 'hidden'])->nullable();
            $table->enum('mutability', ['readonly', 'editable', 'locked'])->nullable();
            $table->boolean('is_required')->nullable();

            $table->timestamps();

            $table->index(['state_field_id', 'role_name']);
        });

        Schema::create('workflow_state_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('workflow_states')->cascadeOnDelete();

            $table->enum('visibility_type', ['roles', 'assignment', 'public', 'custom']);
            $table->json('visibility_config')->nullable();
            $table->boolean('allow_admin_override')->default(true);

            $table->timestamps();

            $table->index('state_id');
        });

        Schema::create('workflow_state_access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('workflow_states')->cascadeOnDelete();

            $table->enum('access_type', ['view', 'edit', 'transition', 'create']);
            $table->string('rule');
            $table->enum('operator', ['or', 'and'])->default('or');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['state_id', 'access_type', 'is_active']);
            $table->index(['state_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_state_access_rules');
        Schema::dropIfExists('workflow_state_visibility');
        Schema::dropIfExists('workflow_state_field_roles');
        Schema::dropIfExists('workflow_state_fields');
    }
};
