<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'department_id', 'penalty_date', 'mistake', 'penalty_type',
    'penalty_level', 'penalty_charges', 'document_path', 'document_original_name',
    'reported_by', 'remarks', 'created_by', 'collection_status', 'collected_by', 'collected_at',
])]
class Penalty extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'penalty_date' => 'date',
            'penalty_charges' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function isCash(): bool
    {
        return $this->penalty_type === 'cash';
    }

    public function isWarning(): bool
    {
        return $this->penalty_type === 'warning';
    }

    public function hasDocument(): bool
    {
        return ! empty($this->document_path);
    }

    public function levelLabel(): string
    {
        return 'Level '.$this->penalty_level;
    }

    public function typeBadgeClass(): string
    {
        return match ($this->penalty_type) {
            'cash' => 'bg-danger',
            'warning' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->collection_status) {
            'pending' => 'bg-warning text-dark',
            'collected' => 'bg-success',
            default => 'bg-secondary',
        };
    }
}
