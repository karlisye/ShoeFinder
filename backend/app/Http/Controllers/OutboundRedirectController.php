<?php

namespace App\Http\Controllers;

use App\Models\RetailerListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutboundRedirectController extends Controller
{
    public function __invoke(
        Request $request,
        RetailerListing $listing,
    ): RedirectResponse {
        $listing->loadMissing('retailer');

        abort_unless($this->isPublic($listing), 404);

        $destination = $this->destination($listing);

        abort_if($destination === null, 404);

        $listing->outboundClicks()->create([
            'locale' => $request->query('locale') === 'en' ? 'en' : 'lv',
            'referrer_path' => $this->referrerPath(
                $request->query('referrer'),
            ),
            'clicked_at' => now(),
        ]);

        return redirect()->away($destination, 302);
    }

    private function isPublic(RetailerListing $listing): bool
    {
        return $listing->active
            && $listing->retailer->active
            && $listing->variant()
                ->where('active', true)
                ->whereHas(
                    'colour',
                    fn ($query) => $query->where('active', true),
                )
                ->whereHas(
                    'shoe',
                    fn ($query) => $query
                        ->where('active', true)
                        ->whereHas(
                            'brand',
                            fn ($query) => $query->where('active', true),
                        )
                        ->whereHas(
                            'category',
                            fn ($query) => $query->where('active', true),
                        ),
                )
                ->exists();
    }

    private function destination(RetailerListing $listing): ?string
    {
        foreach ([$listing->affiliate_url, $listing->product_url] as $url) {
            if (
                is_string($url)
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                return $url;
            }
        }

        return null;
    }

    private function referrerPath(mixed $value): ?string
    {
        if (
            ! is_string($value)
            || $value === ''
            || strlen($value) > 2048
            || ! str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, '\\')
            || preg_match('/[\r\n]/', $value) === 1
        ) {
            return null;
        }

        return $value;
    }
}
