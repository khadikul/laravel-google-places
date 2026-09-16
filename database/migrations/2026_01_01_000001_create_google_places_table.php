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

            $table->string('place_id', 191)->unique();
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->text('google_maps_uri')->nullable();
            $table->text('website_uri')->nullable();
            $table->string('phone_number', 64)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('business_status', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index('rating');
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('google-places.database.tables.places', 'google_places');
    }

    private function schema(): Illuminate\Database\Schema\Builder
    {
        return Schema::connection(config('google-places.database.connection'));
    }
};
