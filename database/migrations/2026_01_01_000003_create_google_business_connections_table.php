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

            $table->string('label')->nullable();
            $table->string('google_account_name', 191)->nullable();
            $table->string('google_email')->nullable();

            /*
             | Encrypted at the model layer with the application APP_KEY before
             | they are written. Stored as text because ciphertext is
             | substantially longer than the token itself.
             */
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();

            $table->string('token_type', 32)->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('notifications_subscribed_at')->nullable();
            $table->string('pubsub_topic')->nullable();

            /*
             | Optional link to whichever model owns the connection in the host
             | application (a user, a team, a tenant). Left generic and
             | unconstrained so the package never dictates your schema.
             */
            $table->nullableMorphs('owner');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('google_account_name');
            $table->index(['is_active', 'revoked_at']);
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('google-places.database.tables.connections', 'google_business_connections');
    }

    private function schema(): Illuminate\Database\Schema\Builder
    {
        return Schema::connection(config('google-places.database.connection'));
    }
};
