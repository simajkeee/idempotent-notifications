<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('notification_batches');
            $table->foreignId('recipient_id')->constrained('subscribers');
            $table->enum('status', NotificationStatus::cases());
            $table->timestamps();

            $table->unique(['batch_id', 'recipient_id']);
            $table->index(['recipient_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
