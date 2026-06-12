<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\NotificationType;
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
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 100)->unique();
            $table->string('request_body_hash', 64);
            $table->enum('channel', Channel::cases());
            $table->enum('type', NotificationType::cases());
            $table->text('message');
            $table->integer('recipients_count');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
