<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\JsonResponse;

class SourceController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = Source::query()
            ->withCount([
                'chunks',
                'chunks as embedded_count' => fn ($query) => $query->whereNotNull('embedding'),
            ])
            ->orderBy('title')
            ->get(['id', 'title', 'short_name', 'unit', 'year', 'source_url', 'retrieved_at', 'updated_at']);

        return response()->json(['data' => $sources]);
    }
}
