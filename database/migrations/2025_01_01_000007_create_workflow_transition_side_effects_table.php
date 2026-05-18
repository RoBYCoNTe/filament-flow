<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transition_side_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('workflow_transitions')->cascadeOnDelete();

            $table->enum('effect_type', ['set_field', 'set_timestamp', 'clear_field', 'increment', 'custom_class']);
            $table->string('field_name');
            $table->string('value_expression')->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['transition_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transition_side_effects');
    }
};
