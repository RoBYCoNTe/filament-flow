<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transition_id')->nullable()->constrained('workflow_transitions')->cascadeOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('workflow_states')->cascadeOnDelete();

            $table->enum('trigger_event', [
                'on_transition',
                'on_state_enter',
                'on_state_exit',
                'on_assignment',
                'on_field_change',
            ])->default('on_transition');

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->enum('timing', ['immediate', 'delayed'])->default('immediate');
            $table->integer('delay_minutes')->nullable();

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'trigger_event']);
            $table->index(['transition_id']);
            $table->index(['state_id']);
        });

        Schema::create('workflow_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('workflow_notifications')->cascadeOnDelete();

            $table->enum('recipient_type', [
                'role',
                'user',
                'trigger_user',
                'assigned_users',
                'record_owner',
                'state_actors',
                'all_involved',
                'involvement_type',
                'custom_field',
                'custom_query',
                'custom_class',
            ])->default('role');

            $table->json('recipient_config')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['notification_id', 'sort_order'], 'notif_recipients_idx');
        });

        Schema::create('workflow_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('workflow_notifications')->cascadeOnDelete();

            $table->enum('channel_type', [
                'database',
                'mail',
            ])->default('database');

            $table->json('channel_config')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['notification_id', 'is_active']);
        });

        Schema::create('workflow_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('workflow_notifications')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('workflow_notification_channels')->cascadeOnDelete();

            $table->string('subject')->nullable();
            $table->text('title')->nullable();
            $table->text('body');
            $table->text('action_text')->nullable();
            $table->text('action_url')->nullable();

            $table->enum('template_engine', ['blade', 'mustache', 'plain'])->default('plain');
            $table->json('variables')->nullable();

            $table->enum('format', ['html', 'markdown', 'plain'])->default('html');

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'channel_id']);
        });

        Schema::create('workflow_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('workflow_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->morphs('notifiable');

            $table->string('channel');
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notif_logs_notifiable_idx');
            $table->index(['status', 'created_at'], 'notif_logs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_notification_logs');
        Schema::dropIfExists('workflow_notification_templates');
        Schema::dropIfExists('workflow_notification_channels');
        Schema::dropIfExists('workflow_notification_recipients');
        Schema::dropIfExists('workflow_notifications');
    }
};
