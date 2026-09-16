<?php

declare(strict_types=1);

namespace App\Livewire\GooglePlaces;

use Illuminate\Support\Collection;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 *   <livewire:google-places.business-search />
 */
class BusinessSearch extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    public ?string $error = null;

    /**
     * Live search would bill a Google request per keystroke, so the search runs
     * on submit only. If you do want it live, debounce it heavily and cache.
     */
    public function search(): void
    {
        $this->error = null;

        $this->validate([
            'query' => ['required', 'string', 'min:2', 'max:200'],
        ]);
    }

    /**
     * @return Collection<int, \Khadikul\GooglePlaces\Data\Place>
     */
    public function getResultsProperty(): Collection
    {
        if (trim($this->query) === '') {
            return collect();
        }

        try {
            return GooglePlaces::search(query: $this->query, limit: 10);
        } catch (GooglePlacesException $e) {
            $this->error = $e->getMessage();

            return collect();
        }
    }

    public function render()
    {
        return view('livewire.google-places.business-search', [
            'results' => $this->results,
        ]);
    }
}
