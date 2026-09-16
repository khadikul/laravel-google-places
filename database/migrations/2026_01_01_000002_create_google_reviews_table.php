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

            /*
             | review_name holds the Google resource name and is the idempotency
             | key for the whole package:
             |
             |   Places API        places/{place}/reviews/{review}
             |   Business Profile  accounts/{account}/locations/{location}/reviews/{review}
             |
             | Both forms are globally unique, so a single unique index is a
             | stricter guarantee than unique(place_id, review_name) would be —
             | and it still holds when place_id is unknown, which is the case
             | for a Business Profile location that has not been matched to a
             | Places place ID.
             */
            $table->string('review_name', 191)->unique();

            $table->string('place_id', 191)->nullable();
            $table->string('location_name', 191)->nullable();
            $table->string('source', 32)->default('places');

            $table->string('author_name')->nullable();
            $table->text('author_uri')->nullable();
            $table->text('author_photo_uri')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('text')->nullable();
            $table->string('language_code', 16)->nullable();

            $table->timestamp('publish_time')->nullable();
            $table->timestamp('update_time')->nullable();
            $table->string('relative_publish_time', 64)->nullable();

            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('google_maps_uri')->nullable();

            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index(['place_id', 'publish_time']);
            $table->index(['location_name', 'publish_time']);
            $table->index(['place_id', 'source']);
            $table->index('rating');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('google-places.database.tables.reviews', 'google_reviews');
    }

    private function schema(): Illuminate\Database\Schema\Builder
    {
        return Schema::connection(config('google-places.database.connection'));
    }
};
