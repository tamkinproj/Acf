<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Foundation;
use App\Models\SiteContent;
use App\Support\ApiResponse;

/** What visitors of the public landing page receive: the PUBLISHED content only, no sign-in. */
class PublicSiteController extends Controller
{
    public function __invoke()
    {
        $fid = Foundation::primary()?->id;   // the foundation the staff work in, the same one the President publishes to
        $row = $fid ? SiteContent::withoutGlobalScopes()->where('foundation_id', $fid)->first() : null;

        $body = ($row && $row->version > 0 && $row->published)
            ? ['version' => $row->version, 'published_at' => $row->published_at?->toIso8601String()] + SiteController::withImages($row->published, $fid)
            : ['version' => 0, 'published_at' => null, 'content' => null, 'images' => []];

        // One public number: how many orphans the foundation looks after. A bare count, nothing about any child.
        // Demo (sample) records are not counted; with no real records the page keeps its own figure.
        $n = $fid ? Child::withoutGlobalScopes()->where('foundation_id', $fid)->where('status', 'active')->where('is_sample', false)->count() : 0;
        $body['stats'] = $n > 0 ? ['orphans' => $n] : null;

        return ApiResponse::ok($body)->header('Cache-Control', 'public, max-age=60')->header('Access-Control-Allow-Origin', '*');
    }
}
