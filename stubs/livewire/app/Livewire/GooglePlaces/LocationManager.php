<?php

declare(strict_types=1);

namespace App\Livewire\GooglePlaces;

use Illuminate\Support\Collection;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Livewire\Component;

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 *   <livewire:google-places.location-manager />
 *
 * Put this behind your own auth middleware: connecting a business profile is an
 * administrative action.
 */
class LocationManager extends Component
{
    public ?string $status = null;

    public ?string $error = null;

    public function connect(string $location, string $account): void
    {
        $this->reset('status', 'error');

        try {
            $model = GooglePlaces::connectLocation($location, $account);

            // Queued, because a busy location pages through many requests.
            GooglePlaces::syncReviews($model->location_name);

            $this->status = 'Location connected. The first sync is running in the background.';
        } catch (GooglePlacesException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function sync(string $location): void
    {
        $this->reset('status', 'error');

        GooglePlaces::syncReviews($location);

        $this->status = 'Sync queued.';
    }

    public function disconnect(string $location): void
    {
        $this->reset('status', 'error');

        GooglePlaces::disconnectLocation($location);

        $this->status = 'Location disconnected. Its reviews were kept.';
    }

    public function getConnectionProperty(): ?GoogleBusinessConnection
    {
        return GoogleBusinessConnection::query()->active()->latest('id')->first();
    }

    /**
     * @return Collection<int, \Khadikul\GooglePlaces\Data\Location>
     */
    public function getLocationsProperty(): Collection
    {
        if ($this->connection === null) {
            return collect();
        }

        try {
            return GooglePlaces::businessAccounts()
                ->flatMap(fn ($account) => GooglePlaces::locations($account->id()))
                ->values();
        } catch (GooglePlacesException $e) {
            $this->error ??= $e->getMessage();

            return collect();
        }
    }

    public function render()
    {
        return view('livewire.google-places.location-manager', [
            'connection' => $this->connection,
            'locations' => $this->locations,
            'connected' => GooglePlaces::connectedLocations(),
        ]);
    }
}
