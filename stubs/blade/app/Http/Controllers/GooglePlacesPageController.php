<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;

/**
 * Published by google-places:scaffold. This file is yours: edit it freely, the
 * package will never touch it again.
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
 * The admin routes should sit behind your own auth middleware: connecting a
 * business profile is an administrative action.
 */
class GooglePlacesPageController extends Controller
{
    public function search(Request $request): View
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

        return view('google-places.search', compact('query', 'results', 'error'));
    }

    public function show(string $placeId): View
    {
        $place = null;
        $photoUrl = null;
        $error = null;

        try {
            $place = GooglePlaces::place($placeId);

            if ($place->photos()->isNotEmpty()) {
                $photoUrl = GooglePlaces::photoUrl($place->photos()->first(), maxWidthPx: 1200);
            }
        } catch (GooglePlacesException $e) {
            $error = $e->getMessage();
        }

        return view('google-places.show', compact('place', 'photoUrl', 'error'));
    }

    public function admin(): View
    {
        $connection = GoogleBusinessConnection::query()->active()->latest('id')->first();

        $accounts = collect();
        $locations = collect();

        if ($connection !== null) {
            try {
                $accounts = GooglePlaces::businessAccounts();
                $locations = $accounts->flatMap(
                    fn ($account) => GooglePlaces::locations($account->id())
                )->values();
            } catch (GooglePlacesException) {
                // Leave the lists empty; the page still renders the connection
                // panel so the user can reconnect.
            }
        }

        return view('google-places.admin', [
            'connection' => $connection,
            'accounts' => $accounts,
            'locations' => $locations,
            'connected' => GooglePlaces::connectedLocations(),
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

            // Queued so a location with thousands of reviews does not block the
            // request. Drop `queue: false` in if you want it inline.
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
