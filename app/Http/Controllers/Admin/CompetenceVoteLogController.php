<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetenceCategory;
use App\Models\CompetenceVote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetenceVoteLogController extends Controller
{
    public function index(Request $request): View
    {
        $base = CompetenceVote::query();

        if ($request->filled('voter_id')) {
            $base->where('voter_id', $request->input('voter_id'));
        }
        if ($request->filled('votee_id')) {
            $base->where('votee_id', $request->input('votee_id'));
        }
        if ($request->filled('category_id')) {
            $base->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('date_from')) {
            $base->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $base->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Per-category totals over the filtered range (SQL aggregate).
        $totalsByCategory = (clone $base)
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $votes = (clone $base)
            ->with([
                'voter' => fn ($q) => $q->withTrashed(),
                'votee' => fn ($q) => $q->withTrashed(),
                'category',
            ])
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);
        $categories = CompetenceCategory::orderBy('name')->get(['id', 'name']);

        return view('admin.competence-votes.index', [
            'votes' => $votes,
            'totalsByCategory' => $totalsByCategory,
            'users' => $users,
            'categories' => $categories,
        ]);
    }
}
