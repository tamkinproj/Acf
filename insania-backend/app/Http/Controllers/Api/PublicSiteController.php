<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Foundation;
use App\Models\SiteContent;
use App\Support\ApiResponse;

/** What visitors of the public landing page receive: the PUBLISHED content only, no sign-in. */
class PublicSiteController extends Controller
{
    public function __invoke()
    {
        $fid = Foundation::query()->orderBy('id')->value('id');
        $row = $fid ? SiteContent::withoutGlobalScopes()->where('foundation_id', $fid)->first() : null;

        $body = ($row && $row->version > 0 && $row->published)
            ? ['version' => $row->version, 'published_at' => $row->published_at?->toIso8601String()] + SiteController::withImages($row->published, $fid)
            : ['version' => 0, 'published_at' => null, 'content' => null, 'images' => []];

        return ApiResponse::ok($body)->header('Cache-Control', 'public, max-age=60')->header('Access-Control-Allow-Origin', '*');
    }
}
