<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWarningLetterRequest;
use App\Http\Requests\Admin\UpdateWarningLetterRequest;
use App\Models\User;
use App\Models\WarningLetter;
use App\Models\WarningLetterEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WarningLetterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage', WarningLetter::class);

        $query = WarningLetter::with(['employee', 'uploader']);

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        $letters = $query->orderByDesc('issued_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $employees = User::withTrashed()->orderBy('name')->get(['id', 'name', 'employee_no']);

        return view('admin.warning-letters.index', compact('letters', 'employees'));
    }

    public function create(): View
    {
        $this->authorize('create', WarningLetter::class);

        $employees = User::orderBy('name')->get(['id', 'name', 'employee_no']);

        return view('admin.warning-letters.create', compact('employees'));
    }

    public function store(StoreWarningLetterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $stored = $this->storeUpload($request->file('file'));

        try {
            DB::transaction(function () use ($data, $stored, $actor) {
                $letter = WarningLetter::create([
                    'user_id' => $data['user_id'],
                    'issued_date' => $data['issued_date'],
                    'level' => $data['level'],
                    'notes' => $data['notes'] ?? null,
                    'uploaded_by' => $actor->id,
                ] + $stored);

                WarningLetterEvent::record(
                    $letter,
                    $actor,
                    WarningLetterEvent::ISSUED,
                    'level: '.$letter->level.'; file: '.$letter->original_filename
                );
            });
        } catch (\Throwable $e) {
            Storage::disk($this->disk())->delete($stored['file_path']);

            throw $e;
        }

        return redirect()->route('admin.warning-letters.index')->with('success', 'Warning letter uploaded.');
    }

    public function edit(WarningLetter $warningLetter): View
    {
        $this->authorize('update', $warningLetter);
        $this->authorize('viewHistory', $warningLetter);

        $employees = User::orderBy('name')->get(['id', 'name', 'employee_no']);
        $events = $warningLetter->events()->with('actor')->latest('id')->limit(200)->get();

        return view('admin.warning-letters.edit', compact('warningLetter', 'employees', 'events'));
    }

    public function update(UpdateWarningLetterRequest $request, WarningLetter $warningLetter): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $disk = Storage::disk($this->disk());

        $warningLetter->fill([
            'user_id' => $data['user_id'],
            'issued_date' => $data['issued_date'],
            'level' => $data['level'],
            'notes' => $data['notes'] ?? null,
        ]);
        $changed = $this->changedFields($warningLetter);

        $stored = null;
        $oldPath = null;
        $oldName = null;
        $keptPath = null;

        if ($request->hasFile('file')) {
            $stored = $this->storeUpload($request->file('file'));
            $oldPath = $warningLetter->file_path;
            $oldName = $warningLetter->original_filename;

            // Keep the old file: rename it instead of deleting it.
            if ($disk->exists($oldPath)) {
                $keptPath = $this->replacedPath($oldPath);

                if (! $disk->move($oldPath, $keptPath)) {
                    $disk->delete($stored['file_path']);

                    throw new \RuntimeException('Could not keep the previous warning letter file.');
                }
            }

            $warningLetter->fill($stored);
        }

        if ($changed === [] && $stored === null) {
            return redirect()->route('admin.warning-letters.index')->with('success', 'No changes to save.');
        }

        try {
            DB::transaction(function () use ($warningLetter, $actor, $changed, $stored, $keptPath, $oldPath, $oldName) {
                // uploaded_by stays the original issuer; only updated_by changes.
                $warningLetter->updated_by = $actor->id;
                $warningLetter->save();

                if ($changed !== []) {
                    WarningLetterEvent::record($warningLetter, $actor, WarningLetterEvent::EDITED, 'changed: '.implode(', ', $changed));
                }

                if ($stored !== null) {
                    WarningLetterEvent::record(
                        $warningLetter,
                        $actor,
                        WarningLetterEvent::FILE_REPLACED,
                        'old file kept as '.($keptPath ?? '(missing on disk: '.$oldPath.')')
                            .'; was: '.$oldName.'; new: '.$stored['original_filename']
                    );
                }
            });
        } catch (\Throwable $e) {
            if ($stored !== null) {
                $disk->delete($stored['file_path']);

                if ($keptPath !== null) {
                    $disk->move($keptPath, $oldPath);
                }
            }

            throw $e;
        }

        return redirect()->route('admin.warning-letters.index')->with('success', 'Warning letter updated.');
    }

    public function destroy(Request $request, WarningLetter $warningLetter): RedirectResponse
    {
        $this->authorize('delete', $warningLetter);

        // Soft delete only: the file stays on the private disk so the record can be restored.
        DB::transaction(function () use ($request, $warningLetter) {
            WarningLetterEvent::record($warningLetter, $request->user(), WarningLetterEvent::DELETED, 'soft delete; file kept on disk');
            $warningLetter->delete();
        });

        return redirect()->route('admin.warning-letters.index')->with('success', 'Warning letter deleted.');
    }

    private function disk(): string
    {
        return config('warning_letters.disk', 'local');
    }

    /** Field names only. The values (and above all the admin-only notes) are never logged. */
    private function changedFields(WarningLetter $letter): array
    {
        $labels = ['user_id' => 'employee', 'issued_date' => 'issued_date', 'level' => 'level', 'notes' => 'notes'];
        $dirty  = array_intersect_key($labels, $letter->getDirty());
        $parts  = [];

        foreach ($dirty as $field => $label) {
            $old = $letter->getOriginal($field);
            $new = $letter->getAttribute($field);

            // Cast dates to string for display
            if ($old instanceof \Illuminate\Support\Carbon || $old instanceof \Carbon\Carbon) {
                $old = $old->format('d M Y');
            }
            if ($new instanceof \Illuminate\Support\Carbon || $new instanceof \Carbon\Carbon) {
                $new = $new->format('d M Y');
            }

            // Resolve user_id to a name
            if ($field === 'user_id') {
                $old = \App\Models\User::find($old)?->name ?? $old;
                $new = \App\Models\User::find($new)?->name ?? $new;
            }

            // Truncate notes to keep the log readable
            if ($field === 'notes') {
                $old = $old ? mb_strimwidth($old, 0, 60, '...') : '(empty)';
                $new = $new ? mb_strimwidth($new, 0, 60, '...') : '(empty)';
            }

            $parts[] = "{$label}: \"{$old}\" -> \"{$new}\"";
        }

        return $parts;
    }

    private function replacedPath(string $oldPath): string
    {
        return config('warning_letters.directory', 'warning-letters').'/replaced/'
            .now()->format('Ymd-His').'-'.basename($oldPath);
    }

    /** @return array{file_path: string, original_filename: string, mime_type: string, file_size: int} */
    private function storeUpload(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $original = Str::limit(basename($file->getClientOriginalName()), 255, '');

        // store() picks a random 40-character name, so the client filename never reaches the disk.
        $path = $file->store(config('warning_letters.directory', 'warning-letters'), $this->disk());

        if ($path === false) {
            throw new \RuntimeException('Could not store the warning letter file.');
        }

        return [
            'file_path' => $path,
            'original_filename' => $original,
            'mime_type' => $mime,
            'file_size' => $size,
        ];
    }
}
