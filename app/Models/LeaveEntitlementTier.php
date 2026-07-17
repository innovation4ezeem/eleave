<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_type_id', 'min_years', 'days', 'employee_status'])]
class LeaveEntitlementTier extends Model
{
    protected function casts(): array
    {
        return [
            'min_years' => 'integer',
            'days' => 'decimal:1',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
