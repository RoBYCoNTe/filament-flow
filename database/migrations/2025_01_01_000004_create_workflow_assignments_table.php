<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_assignments', function (Blueprint $table) {
            $table->id();

            $table->morphs('assignable');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('assignment_type', ['primary', 'secondary', 'viewer'])->default('primary');

            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();

            // Access override columns: null = follow state rules, true = grant access
            $table->boolean('override_view')->nullable();
            $table->boolean('override_edit')->nullable();
            $table->boolean('override_transition')->nullable();

            $table->timestamps();

            $table->unique(['assignable_type', 'assignable_id', 'user_id', 'assignment_type'], 'unique_assignment');
            $table->index('user_id');
            $table->index(['user_id', 'assignable_type', 'override_view'], 'wa_user_override_view_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_assignments');
    }
};
