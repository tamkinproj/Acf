<?php

namespace App\Models\Concerns;

use App\Support\PartnerContext;
use App\Support\Partners;
use Illuminate\Database\Eloquent\Builder;

/**
 * Records that belong to a partner. While a request works in a partner (orphans, applications, education), queries only see
 * that partner's records. A new record is stamped with the request's partner, or Dar Al Ber when there is none (imports, seeders).
 * Use after BelongsToFoundation, so the foundation is known first.
 */
trait BelongsToPartner
{
    protected static function bootBelongsToPartner(): void
    {
        static::addGlobalScope('partner', function (Builder $query) {
            if ($id = PartnerContext::id()) {
                $query->where($query->getModel()->getTable().'.partner_id', $id);
            }
        });

        static::creating(function ($model) {
            if (! $model->partner_id) {
                $fid = (int) ($model->foundation_id ?: auth()->user()?->foundation_id);
                $model->partner_id = PartnerContext::id() ?? ($fid ? Partners::defaultId($fid) : null);
            }
        });
    }

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class);
    }
}
