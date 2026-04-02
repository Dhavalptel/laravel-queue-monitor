<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_monitor_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('processed_per_hour')->default(0);
            $table->unsignedInteger('total_pending')->default(0);
            $table->unsignedInteger('running_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('failure_rate', 5, 2)->default(0);
            $table->decimal('avg_wait_seconds', 8, 2)->default(0);
            $table->json('queue_sizes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_monitor_snapshots');
    }
};
