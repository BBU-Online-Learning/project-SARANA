<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalSearchRequest;
use App\Services\ClassAccessService;
use App\Services\GlobalSearchService;
use Illuminate\Http\Response;

class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, ClassAccessService $access, GlobalSearchService $search): Response
    {
        abort_unless($access->ready($request->user()), 403);
        $term = trim((string) $request->validated('q', ''));
        $activeCategory = $request->validated('category', 'classes');
        $page = (int) $request->validated('page', 1);

        return response()->view('search.index', [
            'term' => $term,
            'results' => mb_strlen($term) >= 2 ? $search->search($request->user(), $term, $activeCategory, $page) : null,
            'limit' => GlobalSearchService::LIMIT,
        ])->header('Cache-Control', 'private, no-store');
    }
}
