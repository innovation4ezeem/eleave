<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'issued_date', 'level', 'notes', 'file_path', 'original_filename',
    'mime_type', 'file_size', 'uploaded_by', 'updated_by',
])]
#[Hidden(['notes'])]
class WarningLetter extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'file_size' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** The original issuer; never changed by later edits or file replacements. */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /** The last admin who edited the letter or replaced its file (null if never changed). */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(WarningLetterEvent::class);
    }
}
