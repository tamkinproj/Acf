<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'type', 'pair_key', 'title', 'created_by', 'last_message_at', 'cover_path', 'cover_v', 'theme'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function members()
    {
        return $this->hasMany(ChatMember::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public static function general(int $foundationId): self
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['foundation_id' => $foundationId, 'pair_key' => 'general'],
            ['type' => 'general', 'title' => 'General']
        );
    }
}
