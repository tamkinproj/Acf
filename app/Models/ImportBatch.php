<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use Syncable, BelongsToFoundation;

    public const UPLOADED = 'uploaded';
    public const MAPPED = 'mapped';
    public const VALIDATED = 'validated';
    public const IMPORTED = 'imported';
    public const CANCELLED = 'cancelled';

    protected $guarded = [];
    protected $hidden = ['storage_path'];
    protected array $auditExcept = ['original_name'];
    protected $casts = ['headers' => 'array', 'mapping' => 'array', 'summary' => 'array', 'imported_at' => 'datetime'];

    public static function syncFields(): array
    {
        return ['program_id', 'file_type', 'status', 'headers', 'mapping', 'row_count', 'summary', 'imported_at'];
    }

    public function auditLabel(): string
    {
        return $this->file_type.' import of '.$this->row_count.' rows';
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id')->orderBy('row_number');
    }
}
