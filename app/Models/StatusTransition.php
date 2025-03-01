<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusTransition extends Model
{
    protected $fillable = [
        'name',
        'from_status_id',
        'to_status_id',
        'board_id',
    ];

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
