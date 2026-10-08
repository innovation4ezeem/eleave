<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Subdivision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubdivisionController extends Controller
{
    public function index(): View
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->with(['subdivisions' => fn ($q) => $q->orderBy('name')])
            ->get();

        return view('admin.subdivisions.index', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name'          => [
                'required', 'string', 'max:100',
                Rule::unique('subdivisions', 'name'),
            ],
        ]);

        Subdivision::create($data);

        return redirect()->route('admin.subdivisions.index')
            ->with('success', 'Subdivision created.');
    }

    public function update(Request $request, Subdivision $subdivision): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subdivisions', 'name')->ignore($subdivision->id),
            ],
        ]);

        $subdivision->update($data);

        return redirect()->route('admin.subdivisions.index')
            ->with('success', 'Subdivision updated.');
    }

    public function destroy(Subdivision $subdivision): RedirectResponse
    {
        $subdivision->delete();

        return redirect()->route('admin.subdivisions.index')
            ->with('success', 'Subdivision deleted.');
    }
}
