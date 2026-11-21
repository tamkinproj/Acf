<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMember extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'last_read_id'];
}
