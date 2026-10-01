<?php

namespace App\Modules\Aytam;

use App\Models\Program;
use Illuminate\Support\Facades\DB;

/**
 * Permanent identifiers: AYT-000001, AYT-000002 ... one counter per program. The counter row is locked by the update
 * that advances it, so two people creating records at the same moment can never receive the same number.
 */
class AytamCodes
{
    public function next(Program $program): string
    {
        $key = 'aytam:'.$program->getKey();
        $prefix = $program->config['code_prefix'] ?? 'AYT';

        return DB::transaction(function () use ($key, $prefix) {
            DB::table('sequences')->insertOrIgnore(['scope_key' => $key, 'value' => 0]);
            DB::table('sequences')->where('scope_key', $key)->increment('value');
            $value = (int) DB::table('sequences')->where('scope_key', $key)->value('value');

            return sprintf('%s-%06d', $prefix, $value);
        });
    }
}
