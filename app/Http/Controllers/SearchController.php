<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Models\Child;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Contracts\View\View;

class SearchController extends Controller
{
    public function index(SearchRequest $request, SearchService $search): View
    {
        $query = $request->searchTerm();
        $filters = $request->filters();

        $results = $search->search($query, $filters, perPage: 10);

        $users = User::query()->orderBy('name')->get();

        return view('search.index', [
            'query' => $query,
            'filters' => $filters,
            'results' => $results,
            'children' => Child::query()->orderBy('name')->get(),
            'users' => $users,
            'showAuthorFilter' => $users->count() > 1,
        ]);
    }
}
