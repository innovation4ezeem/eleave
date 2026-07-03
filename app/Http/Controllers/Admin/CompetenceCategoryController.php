<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompetenceCategoryRequest;
use App\Models\CompetenceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompetenceCategoryController extends Controller
{
    public function index(): View
    {
        $categories = CompetenceCategory::withCount('votes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.competence-categories.index', compact('categories'));
    }

    public function store(StoreCompetenceCategoryRequest $request): RedirectResponse
    {
        $maxSort = (int) CompetenceCategory::max('sort_order');

        CompetenceCategory::create([
            'name' => $request->validated('name'),
            'is_active' => true,
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->route('admin.competence-categories.index')->with('success', 'Category added.');
    }

    public function toggle(CompetenceCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return redirect()->route('admin.competence-categories.index')
            ->with('success', 'Category '.($category->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(CompetenceCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.competence-categories.index')->with('success', 'Category removed.');
    }
}
