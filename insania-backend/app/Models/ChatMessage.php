<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use BelongsToFoundation;

    public $timestamps = false;

    protected $fillable = ['foundation_id', 'conversation_id', 'user_id', 'body', 'card_kind', 'card_id', 'card_extra', 'created_at', 'file_kind', 'file_path', 'file_mime', 'file_size', 'file_ms', 'file_peaks'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
