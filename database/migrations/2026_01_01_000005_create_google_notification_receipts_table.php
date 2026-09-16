<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema()->create($this->table(), function (Blueprint $table): void {
            $table->id();

            // The unique index is what makes duplicate Pub/Sub deliveries safe.
            $table->string('message_id', 191)->unique();

            $table->string('notification_type', 64)->nullable();
            $table->string('location_name', 191)->nullable();
            $table->string('review_name', 191)->nullable();
            $table->string('subscription')->nullable();

            $table->json('payload')->nullable();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index('received_at');
            $table->index(['notification_type', 'received_at']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('google-places.database.tables.notifications', 'google_notification_receipts');
    }

    private function schema(): Illuminate\Database\Schema\Builder
    {
        return Schema::connection(config('google-places.database.connection'));
    }
};
