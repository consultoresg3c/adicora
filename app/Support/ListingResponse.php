<?php

namespace App\Support;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/** Solo los resultados del listado se actualizan por fetch; el resto sigue siendo una página Blade. */
class ListingResponse
{
    public static function view(string $name, array $data = []): View|JsonResponse
    {
        $view = view($name, $data);
        if (request()->header('X-Buko-Listing') !== 'true' || ! request()->expectsJson()) {
            return $view;
        }

        return AsyncResponse::success('Listado actualizado.', ['html' => $view->fragment('list-results')])
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Accept, X-Buko-Listing');
    }
}
