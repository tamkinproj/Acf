<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

/**
 * A partner charity the foundation works with (Dar Al Ber Society, Dubai Charity, ...). Orphans, applications and education
 * records belong to one partner. A partner is never deleted: it is active, paused or not set up yet.
 */
class Partner extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'code', 'name_en', 'name_ar', 'logo', 'form_key', 'status', 'sort', 'paused_at', 'paused_by'];

    protected $casts = ['paused_at' => 'datetime', 'sort' => 'integer'];

    public function toApi(): array
    {
        return [
            'code' => $this->code, 'name_en' => $this->name_en, 'name_ar' => $this->name_ar, 'logo' => $this->logo,
            'status' => $this->status, 'form_key' => $this->form_key, 'paused_at' => $this->paused_at?->toIso8601String(),
        ];
    }
}
