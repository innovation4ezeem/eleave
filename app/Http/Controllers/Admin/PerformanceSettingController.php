<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePerformanceComponentRequest;
use App\Http\Requests\Admin\StorePerformanceInputRequest;
use App\Models\PerformanceComponent;
use App\Models\PerformanceInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerformanceSettingController extends Controller
{
    public function index(): View
    {
        $inputs = PerformanceInput::orderBy('sort_order')->orderBy('key')->get();
        $components = PerformanceComponent::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.performance-settings.index', compact('inputs', 'components'));
    }

    public function storeInput(StorePerformanceInputRequest $request): RedirectResponse
    {
        $maxSort = (int) PerformanceInput::max('sort_order');

        PerformanceInput::create($request->validated() + ['sort_order' => $maxSort + 1]);

        return redirect()->route('admin.performance-settings.index')->with('success', 'Input field added.');
    }

    public function destroyInput(PerformanceInput $input): RedirectResponse
    {
        // Block removal while any component expression still references this key.
        $referencing = PerformanceComponent::where('expression', 'like', '%'.$input->key.'%')->get()
            ->filter(fn ($c) => $this->expressionUsesKey($c->expression, $input->key));

        if ($referencing->isNotEmpty()) {
            return redirect()->route('admin.performance-settings.index')
                ->with('error', "Cannot remove \"{$input->key}\" — it is used by: ".$referencing->pluck('name')->implode(', ').'.');
        }

        $input->delete();

        return redirect()->route('admin.performance-settings.index')->with('success', 'Input field removed.');
    }

    public function storeComponent(StorePerformanceComponentRequest $request): RedirectResponse
    {
        $maxSort = (int) PerformanceComponent::max('sort_order');

        PerformanceComponent::create([
            'name' => $request->validated('name'),
            'expression' => $request->validated('expression'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->route('admin.performance-settings.index')->with('success', 'Scoring component added.');
    }

    public function updateComponent(StorePerformanceComponentRequest $request, PerformanceComponent $component): RedirectResponse
    {
        $component->update([
            'name' => $request->validated('name'),
            'expression' => $request->validated('expression'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.performance-settings.index')->with('success', 'Scoring component updated.');
    }

    public function destroyComponent(PerformanceComponent $component): RedirectResponse
    {
        $component->delete();

        return redirect()->route('admin.performance-settings.index')->with('success', 'Scoring component removed.');
    }

    /**
     * Whether an expression references the exact key as a standalone identifier
     * (so "error" doesn't match inside "error_rate").
     */
    private function expressionUsesKey(string $expression, string $key): bool
    {
        return (bool) preg_match('/(?<![a-z0-9_])'.preg_quote($key, '/').'(?![a-z0-9_])/i', $expression);
    }
}
