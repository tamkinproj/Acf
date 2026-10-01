<?php

namespace App\Models;

use App\Sync\Concerns\HasSyncMetadata;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How one organization relates to one program (program-specific configuration lives here, not on the organization). */
class ProgramOrganization extends Model
{
    use HasSyncMetadata, BelongsToFoundation;

    public const RELATIONSHIPS = ['partner', 'funder', 'implementing_partner', 'school', 'referral', 'other'];

    protected $guarded = [];
    protected $casts = ['config' => 'array'];

    public static function syncFields(): array
    {
        return ['program_id', 'organization_id', 'relationship', 'status', 'config', 'notes'];
    }

    public function auditLabel(): string
    {
        return ($this->organization?->name ?? $this->organization_id).' / '.($this->program?->name ?? $this->program_id);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
