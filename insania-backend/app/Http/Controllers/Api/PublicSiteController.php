<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Foundation;
use App\Models\Project;
use App\Models\Sponsor;
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

        // Live figures for the landing page: bare counts, nothing about any child or family.
        // Demo (sample) records are not counted. A figure with no real data behind it is left out,
        // and the page keeps its own temporary number for it.
        foreach ($body['content']['team'] ?? [] as $i => $m) {   // which account may edit a card stays inside the staff app
            unset($body['content']['team'][$i]['user_id']);
        }
        $body['stats'] = $fid ? self::stats($fid) : null;

        return ApiResponse::ok($body)->header('Cache-Control', 'public, max-age=60')->header('Access-Control-Allow-Origin', '*');
    }

    /** Keys are the landing page's number slots. Only figures above zero are returned. */
    public static function stats(int $fid): ?array
    {
        $kids = Child::withoutGlobalScopes()->where('foundation_id', $fid)->where('status', 'active')->where('is_sample', false);
        $projects = Project::withoutGlobalScopes()->where('foundation_id', $fid);
        $all = [
            'hero_orphans' => (clone $kids)->count(),
            'hero_families' => (clone $kids)->whereNotNull('guardian_id')->distinct()->count('guardian_id'),
            'hero_partners' => Sponsor::withoutGlobalScopes()->where('foundation_id', $fid)->count(),
            'proj_done' => (clone $projects)->where('status', 'completed')->count(),
            'proj_communities' => (clone $projects)->whereNotNull('location')->where('location', '!=', '')->distinct()->count('location'),
        ];
        $real = array_filter($all, fn ($n) => $n > 0);

        return $real ?: null;
    }
}
