<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who held a record before, who holds it now, and what the previous holder kept: the
 * history of a handover. The owner column reads it to say, beside the name, that the
 * record changed hands — and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_owner_changes', function (Blueprint $table) {
            $table->id();

            $table->string('changeable_type');
            $table->string('changeable_id', 36);
            $table->index(['changeable_type', 'changeable_id'], 'woc_changeable_idx');

            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            // The column the owner lives in, when the host keeps it outside the default one.
            $table->string('owner_field')->nullable();

            $table->string('retention')->default('none');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['changeable_type', 'changeable_id', 'changed_at'], 'woc_changeable_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_owner_changes');
    }
};
