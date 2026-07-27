<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalogue\Read\CatalogueReadService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogFiltersRequest;
use App\Http\Requests\Api\V1\ListShoesRequest;
use App\Http\Requests\Api\V1\ShowShoeRequest;
use Illuminate\Http\JsonResponse;

class CatalogueController extends Controller
{
    public function index(
        ListShoesRequest $request,
        CatalogueReadService $catalogue,
    ): JsonResponse {
        return response()->json($catalogue->shoes($request->filters()));
    }

    public function show(
        ShowShoeRequest $request,
        CatalogueReadService $catalogue,
        string $slug,
    ): JsonResponse {
        $response = $catalogue->shoe($slug, $request->options());

        if ($response !== null) {
            return response()->json($response);
        }

        $message = $request->input('locale') === 'en'
            ? 'Shoe not found.'
            : 'Apavi nav atrasti.';

        return response()->json([
            'error' => [
                'code' => 'shoe_not_found',
                'message' => $message,
            ],
        ], 404);
    }

    public function filters(
        CatalogFiltersRequest $request,
        CatalogueReadService $catalogue,
    ): JsonResponse {
        return response()->json($catalogue->filters($request->options()));
    }
}
