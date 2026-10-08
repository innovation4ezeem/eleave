<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['warning_letter_id', 'user_id', 'action', 'details'])]
class WarningLetterEvent extends Model
{
    public const UPDATED_AT = null;

    public const ISSUED = 'issued';
    public const EDITED = 'edited';
    public const FILE_REPLACED = 'file_replaced';
    public const DELETED = 'deleted';
    public const DOWNLOADED = 'downloaded';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Details are short, and never carry the admin-only notes. */
    public static function record(WarningLetter $letter, User $actor, string $action, ?string $details = null): self
    {
        return static::create([
            'warning_letter_id' => $letter->id,
            'user_id' => $actor->id,
            'action' => $action,
            'details' => $details === null ? null : Str::limit($details, 255, ''),
        ]);
    }

    public function warningLetter(): BelongsTo
    {
        return $this->belongsTo(WarningLetter::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function label(): string
    {
        return match ($this->action) {
            self::ISSUED => 'Issued',
            self::EDITED => 'Edited',
            self::FILE_REPLACED => 'File replaced',
            self::DELETED => 'Deleted',
            self::DOWNLOADED => 'Downloaded',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
