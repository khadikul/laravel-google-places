<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;

/**
 * Published by google-places:scaffold. This file is yours.
 *
 * Wire it up in routes/web.php:
 *
 *   Route::get('/places', [GooglePlacesPageController::class, 'search'])
 *       ->name('google-places.page.search');
 *   Route::get('/places/{placeId}', [GooglePlacesPageController::class, 'show'])
 *       ->name('google-places.page.show');
 *
 *   Route::middleware(['auth'])->group(function () {
 *       Route::get('/admin/google', [GooglePlacesPageController::class, 'admin'])
 *           ->name('google-places.page.admin');
 *       Route::post('/admin/google/connect', [GooglePlacesPageController::class, 'connect'])
 *           ->name('google-places.locations.connect');
 *       Route::post('/admin/google/sync', [GooglePlacesPageController::class, 'sync'])
 *           ->name('google-places.locations.sync');
 *   });
 *
 * Every DTO here implements Arrayable and JsonSerializable, so it crosses to
 * the client as plain JSON with snake_case keys. The components expect exactly
 * the shapes produced below.
 */
class GooglePlacesPageController extends Controller
{
    public function search(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $query = $validated['q'] ?? null;
        $results = collect();
        $error = null;

        if (filled($query)) {
            try {
                $results = GooglePlaces::search(query: $query, limit: 10);
            } catch (GooglePlacesException $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('GooglePlaces/Search', [
            'query' => $query,
            'results' => $results->map->toArray()->values(),
            'error' => $error,
        ]);
    }

    public function show(string $placeId): Response
    {
        $place = null;
        $photoUrl = null;
        $error = null;

        try {
            $found = GooglePlaces::place($placeId);
            $place = $found->toArray();

            if ($found->photos()->isNotEmpty()) {
                $photoUrl = GooglePlaces::photoUrl($found->photos()->first(), maxWidthPx: 1200);
                $place['photo_attribution'] = $found->photos()->first()->attributionNames();
            }
        } catch (GooglePlacesException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('GooglePlaces/Show', [
            'placeId' => $placeId,
            'place' => $place,
            'photoUrl' => $photoUrl,
            'error' => $error,
            // Read back from your own database: no API call, no billing.
            'reviews' => GooglePlaces::reviews($placeId, limit: 50)
                ->map(fn ($review) => $review->toData()->toArray())
                ->values(),
        ]);
    }

    public function admin(): Response
    {
        $connection = GoogleBusinessConnection::query()->active()->latest('id')->first();

        $locations = collect();
        $error = null;

        if ($connection !== null) {
            try {
                $locations = GooglePlaces::businessAccounts()
                    ->flatMap(fn ($account) => GooglePlaces::locations($account->id()))
                    ->map->toArray()
                    ->values();
            } catch (GooglePlacesException $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('GooglePlaces/Admin', [
            // toArray() on the model strips the token columns.
            'connection' => $connection?->toArray(),
            'locations' => $locations,
            'connected' => GooglePlaces::connectedLocations()->map->toArray()->values(),
            'error' => $error,
            'connectUrl' => route('google-places.oauth.redirect'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location' => ['required', 'string'],
            'account' => ['required', 'string'],
        ]);

        try {
            $location = GooglePlaces::connectLocation($validated['location'], $validated['account']);

            GooglePlaces::syncReviews($location->location_name);
        } catch (GooglePlacesException $e) {
            return back()->with('status', 'Could not connect: '.$e->getMessage());
        }

        return back()->with('status', 'Location connected. The first sync is running in the background.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location' => ['required', 'string'],
        ]);

        GooglePlaces::syncReviews($validated['location']);

        return back()->with('status', 'Sync queued.');
    }
}
