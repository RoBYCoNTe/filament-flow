<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The channels of a notification stop being a closed list: beside the database and the
 * mail the engine delivers itself, a host registers drivers for its own channels (a PEC,
 * an external service), and the name of the channel is stored as it is declared.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_notification_channels')) {
            return;
        }

        Schema::table('workflow_notification_channels', function (Blueprint $table) {
            $table->string('channel_type')->default('database')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('workflow_notification_channels')) {
            return;
        }

        Schema::table('workflow_notification_channels', function (Blueprint $table) {
            $table->enum('channel_type', ['database', 'mail'])->default('database')->change();
        });
    }
};
