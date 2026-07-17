<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['voter_id', 'votee_id', 'category_id'])]
class CompetenceVote extends Model
{
    public const UPDATED_AT = null;

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function votee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'votee_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CompetenceCategory::class, 'category_id');
    }
}
