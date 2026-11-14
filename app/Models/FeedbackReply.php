<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['author_id', 'message', 'from_sender'])]
class FeedbackReply extends Model
{
    protected $attributes = ['from_sender' => false];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'from_sender' => 'boolean'];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
