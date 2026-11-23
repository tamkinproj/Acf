<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use App\Models\Concerns\BelongsToPartner;
use Illuminate\Database\Eloquent\Model;

/** What is written for one orphan's yearly partner report (see the orphan_reports migration). */
class OrphanReport extends Model
{
    use BelongsToFoundation;
    use BelongsToPartner;

    protected $fillable = ['foundation_id', 'partner_id', 'child_id', 'year', 'responsible_name', 'responsible_relation', 'choices', 'changes', 'comment', 'message', 'codes', 'status', 'approved_by', 'approved_at', 'updated_by'];

    protected $casts = ['choices' => 'array', 'approved_at' => 'datetime', 'year' => 'integer'];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }
}
