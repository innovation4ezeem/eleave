<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreCompetenceVoteRequest;
use App\Models\CompetenceCategory;
use App\Models\CompetenceVote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetenceController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();

        // Totals I received per category, optionally within a date range. No voter identities.
        $totalsQuery = CompetenceVote::query()
            ->selectRaw('category_id, COUNT(*) as total')
            ->where('votee_id', $me->id)
            ->groupBy('category_id');

        if ($request->filled('date_from')) {
            $totalsQuery->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $totalsQuery->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $totalsByCategory = $totalsQuery->pluck('total', 'category_id');

        $categories = CompetenceCategory::active()->orderBy('sort_order')->orderBy('name')->get();

        // Colleagues I can vote for: any active non-admin user except myself.
        $colleagues = User::whereNotNull('employee_status')
            ->whereIn('role', ['employee', 'hod'])
            ->where('id', '!=', $me->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('employee.competence.index', [
            'categories' => $categories,
            'totalsByCategory' => $totalsByCategory,
            'colleagues' => $colleagues,
            'dateFrom' => $request->input('date_from'),
            'dateTo' => $request->input('date_to'),
        ]);
    }

    public function vote(StoreCompetenceVoteRequest $request): RedirectResponse
    {
        CompetenceVote::create([
            'voter_id' => $request->user()->id,
            'votee_id' => $request->validated('votee_id'),
            'category_id' => $request->validated('category_id'),
        ]);

        return redirect()->route('competence.index')->with('success', 'Your vote has been recorded.');
    }
}
