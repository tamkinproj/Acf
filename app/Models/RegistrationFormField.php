<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class RegistrationFormField extends Model
{
    use Syncable, BelongsToFoundation;

    protected $guarded = [];
    protected $casts = ['required' => 'boolean', 'options' => 'array'];

    public static function syncFields(): array
    {
        return ['program_id', 'form_id', 'section_id', 'field_key', 'label', 'type', 'required', 'help_text', 'options', 'maps_to', 'document_type', 'position'];
    }

    public function isAudited(): bool
    {
        return false;
    }
}
