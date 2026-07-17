<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePerformanceEntryRequest;
use App\Models\PerformanceEntry;
use App\Models\PerformanceInput;
use App\Models\User;
use App\Services\PerformanceScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceEntryController extends Controller
{
    public function __construct(private PerformanceScoreService $scoreService)
    {
    }

    public function index(Request $request): View
    {
        $query = PerformanceEntry::with(['user' => fn ($q) => $q->withTrashed(), 'enteredBy'])
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('year')) {
            $query->where('year', $request->input('year'));
        }
        if ($request->filled('month')) {
            $query->where('month', $request->input('month'));
        }

        $entries = $query->paginate(30)->withQueryString();
        $employees = User::whereIn('role', ['employee', 'hod'])->orderBy('name')->get(['id', 'name']);

        // Yearly summary: total and average per employee per year, honoring the
        // employee/year filters (month is ignored — this is a whole-year rollup).
        // AVG divides by the number of months that have an entry, not by 12.
        $summaries = PerformanceEntry::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->input('year')))
            ->selectRaw('user_id, year, COUNT(*) as months, SUM(total_score) as total, AVG(total_score) as average')
            ->groupBy('user_id', 'year')
            ->orderByDesc('year')
            ->get();

        $summaryNames = User::withTrashed()
            ->whereIn('id', $summaries->pluck('user_id')->unique())
            ->pluck('name', 'id');

        return view('admin.performance-entries.index', compact('entries', 'employees', 'summaries', 'summaryNames'));
    }

    public function create(): View
    {
        $employees = User::whereIn('role', ['employee', 'hod'])->orderBy('name')->get(['id', 'name']);
        $inputs = PerformanceInput::orderBy('sort_order')->get();

        return view('admin.performance-entries.create', [
            'employees' => $employees,
            'inputs' => $inputs,
            'entry' => null,
        ]);
    }

    public function store(StorePerformanceEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $values = $this->normalizeValues($data['values'] ?? []);

        $computed = $this->scoreService->compute($values);

        PerformanceEntry::updateOrCreate(
            ['user_id' => $data['user_id'], 'year' => $data['year'], 'month' => $data['month']],
            [
                'values' => $values,
                'scores' => $computed['scores'],
                'total_score' => $computed['total'],
                'entered_by' => auth()->id(),
            ],
        );

        return redirect()->route('admin.performance-entries.index')
            ->with('success', 'Performance entry saved (total score: '.$computed['total'].($computed['complete'] ? '' : ' — partial, some inputs still blank').').');
    }

    /**
     * Keep blank inputs as null (pending) instead of coercing them to 0.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, float|null>
     */
    private function normalizeValues(array $raw): array
    {
        $values = [];
        foreach ($raw as $key => $value) {
            $values[$key] = ($value === null || $value === '') ? null : (float) $value;
        }

        return $values;
    }

    public function edit(PerformanceEntry $performanceEntry): View
    {
        $employees = User::whereIn('role', ['employee', 'hod'])->orderBy('name')->get(['id', 'name']);
        $inputs = PerformanceInput::orderBy('sort_order')->get();

        return view('admin.performance-entries.create', [
            'employees' => $employees,
            'inputs' => $inputs,
            'entry' => $performanceEntry,
        ]);
    }

    public function update(StorePerformanceEntryRequest $request, PerformanceEntry $performanceEntry): RedirectResponse
    {
        $data = $request->validated();
        $values = $this->normalizeValues($data['values'] ?? []);

        $computed = $this->scoreService->compute($values);

        $performanceEntry->update([
            'user_id' => $data['user_id'],
            'year' => $data['year'],
            'month' => $data['month'],
            'values' => $values,
            'scores' => $computed['scores'],
            'total_score' => $computed['total'],
            'entered_by' => auth()->id(),
        ]);

        return redirect()->route('admin.performance-entries.index')
            ->with('success', 'Performance entry updated (total score: '.$computed['total'].($computed['complete'] ? '' : ' — partial, some inputs still blank').').');
    }

    public function destroy(PerformanceEntry $performanceEntry): RedirectResponse
    {
        $performanceEntry->delete();

        return redirect()->route('admin.performance-entries.index')->with('success', 'Performance entry deleted.');
    }
}
