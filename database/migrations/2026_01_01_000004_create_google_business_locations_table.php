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

            $table->unsignedBigInteger('connection_id');

            // Fully qualified: accounts/{account}/locations/{location}
            $table->string('location_name', 191)->unique();
            $table->string('account_name', 191)->nullable();

            // Bridges a Business Profile location to its public Places entry,
            // taken from the location metadata Google returns.
            $table->string('place_id', 191)->nullable();

            $table->string('title')->nullable();
            $table->string('store_code', 191)->nullable();
            $table->text('address')->nullable();
            $table->text('website_uri')->nullable();
            $table->string('phone_number', 64)->nullable();
            $table->text('maps_uri')->nullable();

            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('total_review_count')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('connection_id');
            $table->index('place_id');
            $table->index(['sync_enabled', 'last_synced_at']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('google-places.database.tables.locations', 'google_business_locations');
    }

    private function schema(): Illuminate\Database\Schema\Builder
    {
        return Schema::connection(config('google-places.database.connection'));
    }
};
