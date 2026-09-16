<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Khadikul\GooglePlaces\Models\Concerns\UsesPackageConnection;

/**
 * An OAuth connection to one Google account, owned entirely by this
 * application.
 *
 * The token columns use Laravel's "encrypted" cast, so they are encrypted with
 * this application's APP_KEY before they ever reach the database. They are only
 * ever sent back to Google.
 *
 * @property int $id
 * @property ?string $google_account_name
 * @property ?string $google_email
 * @property ?string $access_token
 * @property ?string $refresh_token
 * @property ?\Illuminate\Support\Carbon $expires_at
 * @property ?\Illuminate\Support\Carbon $revoked_at
 * @property bool $is_active
 * @property ?array<int, string> $scopes
 */
class GoogleBusinessConnection extends Model
{
    use UsesPackageConnection;

    protected $table = null;

    protected $guarded = [];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'notifications_subscribed_at' => 'datetime',
    ];

    protected function packageTableKey(): string
    {
        return 'connections';
    }

    /**
     * @return HasMany<GoogleBusinessLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(GoogleBusinessLocation::class, 'connection_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || $this->is_active === false;
    }

    public function isExpired(int $leewaySeconds = 0): bool
    {
        if ($this->expires_at === null) {
            // Unknown expiry: treat as expired so it is refreshed defensively.
            return true;
        }

        return $this->expires_at->subSeconds($leewaySeconds)->isPast();
    }

    public function hasRefreshToken(): bool
    {
        return is_string($this->refresh_token) && $this->refresh_token !== '';
    }

    public function markRevoked(): void
    {
        $this->forceFill([
            'access_token' => null,
            'revoked_at' => now(),
            'is_active' => false,
        ])->save();
    }

    /**
     * Never let a token reach a log, an exception message or a JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        unset($array['access_token'], $array['refresh_token']);

        return $array;
    }

    public function __toString(): string
    {
        return sprintf('GoogleBusinessConnection#%s', $this->getKey() ?? 'new');
    }
}
