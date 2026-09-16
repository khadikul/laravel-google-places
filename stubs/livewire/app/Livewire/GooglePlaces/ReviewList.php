<?php

declare(strict_types=1);

namespace App\Livewire\GooglePlaces;

use Illuminate\Support\Collection;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Models\GooglePlace;
use Livewire\Component;

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 *   <livewire:google-places.review-list :place-id="$placeId" />
 *
 * Reads from your own database, so paging and filtering cost nothing.
 */
class ReviewList extends Component
{
    public string $placeId = '';

    public int $perPage = 6;

    public ?int $minRating = null;

    public function loadMore(): void
    {
        $this->perPage += 6;
    }

    public function filterBy(?int $rating): void
    {
        $this->minRating = $rating;
        $this->perPage = 6;
    }

    /**
     * @return Collection<int, \Khadikul\GooglePlaces\Models\GoogleReview>
     */
    public function getReviewsProperty(): Collection
    {
        $reviews = GooglePlaces::reviews($this->placeId, limit: $this->perPage + 1);

        if ($this->minRating !== null) {
            $reviews = $reviews->filter(fn ($review) => (int) $review->rating >= $this->minRating);
        }

        return $reviews->take($this->perPage)->values();
    }

    public function getHasMoreProperty(): bool
    {
        return GooglePlaces::reviews($this->placeId, limit: $this->perPage + 1)->count() > $this->perPage;
    }

    public function render()
    {
        return view('livewire.google-places.review-list', [
            'reviews' => $this->reviews,
            'hasMore' => $this->hasMore,
            'place' => GooglePlace::query()->where('place_id', $this->placeId)->first(),
        ]);
    }
}
