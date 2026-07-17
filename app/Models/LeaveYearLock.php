<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_year', 'locked_at', 'locked_by'])]
class LeaveYearLock extends Model
{
    /** @var array<int, bool>|null Request-scoped cache of locked leave years. */
    private static ?array $cache = null;

    protected function casts(): array
    {
        return [
            'leave_year' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public static function isLocked(int $leaveYear): bool
    {
        if (self::$cache === null) {
            self::$cache = self::pluck('leave_year')
                ->flip()
                ->map(fn () => true)
                ->all();
        }

        return isset(self::$cache[$leaveYear]);
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }
}
