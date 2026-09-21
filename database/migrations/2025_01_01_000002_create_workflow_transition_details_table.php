<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transition_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('workflow_transitions')->cascadeOnDelete();

            $table->string('field_name');
            $table->string('field_type');
            $table->string('label');

            $table->string('model_attribute')->nullable();
            $table->enum('mapping_type', ['direct', 'transform', 'computed', 'relationship', 'assignment', 'custom', 'ignore'])->default('direct');
            $table->json('mapping_config')->nullable();

            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->string('custom_validation_class')->nullable();

            $table->integer('sort_order')->default(0);
            $table->json('field_config')->nullable();
            $table->boolean('save_to_model')->default(true);

            $table->timestamps();

            $table->index(['transition_id', 'sort_order']);
        });

        Schema::create('workflow_transition_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('workflow_transitions')->cascadeOnDelete();

            $table->enum('permission_type', ['role', 'assignment', 'custom']);
            $table->string('permission_value')->nullable();
            $table->boolean('require_all')->default(false);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('transition_id');
        });

        Schema::create('workflow_transition_validation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('workflow_transitions')->cascadeOnDelete();

            $table->string('field_name');
            $table->json('rules');
            $table->string('custom_message')->nullable();

            // How `rules` is interpreted: Laravel rules, names resolved by the
            // validation rule registry, or a single formula expression.
            $table->string('rule_type', 24)->default('laravel');

            // Formula that must hold for the rule to be evaluated (conditional
            // validation); null means "always".
            $table->string('condition', 1024)->nullable();

            // Label of the target field, shown in the validation summary for
            // paths that have no form component (virtual keys).
            $table->string('label')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // A field can carry more than one rule entry (e.g. a Laravel rule and
            // an expression rule, with different conditions and messages), so the
            // field name is not unique: the order is what the engine follows.
            $table->index(['transition_id', 'field_name'], 'wf_trans_val_rules_transition_field_index');
            $table->index(['transition_id', 'sort_order'], 'wf_trans_val_rules_transition_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transition_validation_rules');
        Schema::dropIfExists('workflow_transition_permissions');
        Schema::dropIfExists('workflow_transition_fields');
    }
};
