<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationFormSection extends Model
{
    use Syncable, BelongsToFoundation;

    protected $guarded = [];

    public static function syncFields(): array
    {
        return ['program_id', 'form_id', 'title', 'description', 'position'];
    }

    public function isAudited(): bool
    {
        return false;   // the form's own publish/unpublish history is the audit trail; each drag-and-drop would only be noise
    }

    public function fields(): HasMany
    {
        return $this->hasMany(RegistrationFormField::class, 'section_id')->orderBy('position');
    }
}
