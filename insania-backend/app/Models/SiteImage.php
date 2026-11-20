<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class SiteImage extends Model
{
    use BelongsToFoundation;

    public $timestamps = false;

    protected $fillable = ['foundation_id', 'filename', 'mime', 'width', 'height', 'bytes', 'uploaded_by'];

    public function url(): string
    {
        return rtrim((string) config('app.url'), '/').'/uploads/site/'.$this->filename;
    }
}
