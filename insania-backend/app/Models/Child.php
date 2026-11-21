<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    use BelongsToFoundation;

    public const DOCUMENT_TYPES = ['birth_certificate', 'father_death_certificate', 'school_record', 'guardian_id'];

    protected $fillable = [
        'foundation_id', 'ref', 'name_en', 'name_ar', 'gender', 'birth_date', 'city', 'health_status', 'grade',
        'mother_name', 'mother_marital_status', 'father_death_date', 'brothers', 'sisters', 'guardian_id', 'status',
    ];

    protected $casts = ['birth_date' => 'date:Y-m-d', 'father_death_date' => 'date:Y-m-d'];

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function documents()
    {
        return $this->hasMany(ChildDocument::class);
    }

    public function bankAccount()
    {
        return $this->hasOne(BankAccount::class);
    }

    public function histories()
    {
        return $this->hasMany(ChannelHistory::class)->orderBy('since');
    }

    public function sponsorships()
    {
        return $this->hasMany(Sponsorship::class);
    }

    public function sponsorship()
    {
        // The current arrangement: active, or pending while a new child is still being set up.
        return $this->hasOne(Sponsorship::class)->whereIn('status', ['active', 'pending'])->latestOfMany();
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $q;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $q->where(function ($w) use ($like) {
            $w->where('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('ref', 'like', $like)
                ->orWhereHas('guardian', fn ($g) => $g->where('name', 'like', $like));
        });
    }

    /** Row for the list screens. */
    public function toListApi(): array
    {
        $s = $this->sponsorship;

        return [
            'id' => $this->id,
            'ref' => $this->ref,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'guardian' => $this->guardian?->name,
            'pay_type' => $s?->pay_type,
            'monthly_amount' => $s ? (float) $s->monthly_amount : null,
            'bank_verified' => $this->bankAccount?->verified,
            'status' => $this->status,
            'age' => $this->birth_date?->age,
        ];
    }

    /** Full profile, in the sections the staff app shows. */
    public function toProfileApi(bool $withDocuments = true): array
    {
        $this->loadMissing(['guardian', 'bankAccount', 'histories', 'sponsorship.sponsor', 'documents']);
        $s = $this->sponsorship;
        $docs = $this->documents->keyBy('type');

        return $this->toListApi() + [
            'created_at' => $this->created_at?->toDateString(), 'updated_at' => $this->updated_at?->toDateString(),
            'personal' => [
                'gender' => $this->gender, 'birth_date' => $this->birth_date?->toDateString(), 'city' => $this->city,
                'health_status' => $this->health_status, 'grade' => $this->grade,
            ],
            'family' => [
                'mother_name' => $this->mother_name, 'mother_marital_status' => $this->mother_marital_status,
                'father_death_date' => $this->father_death_date?->toDateString(), 'brothers' => $this->brothers, 'sisters' => $this->sisters,
            ],
            'guardian_detail' => $this->guardian ? ['name' => $this->guardian->name, 'relation' => $this->guardian->relation, 'phone' => $this->guardian->phone] : null,
            'bank' => $this->bankAccount ? [
                'holder' => $this->bankAccount->holder, 'bank_name' => $this->bankAccount->bank_name,
                'last4' => $this->bankAccount->last4, 'verified' => $this->bankAccount->verified,
            ] : null,
            'channel' => $s ? ['channel' => $s->channel, 'sponsor' => $s->sponsor?->name, 'since' => $s->start_date?->toDateString()] : null,
            'channel_history' => $this->histories->map(fn ($h) => ['channel' => $h->channel, 'since' => $h->since->toDateString()])->values(),
            'documents' => $withDocuments ? collect(self::DOCUMENT_TYPES)->map(fn ($t) => [
                'type' => $t, 'on_file' => (bool) $docs->get($t)?->path,
            ])->values() : null,
        ];
    }
}
