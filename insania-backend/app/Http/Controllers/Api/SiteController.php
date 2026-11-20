<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Foundation;
use App\Models\SiteContent;
use App\Models\SiteVersion;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\SiteContentRules;
use App\Support\SiteImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The public landing page's content. The President Admin edits a DRAFT, previews it, then publishes.
 * Visitors only ever receive the published version.
 */
class SiteController extends Controller
{
    private function row(Request $request): SiteContent
    {
        return SiteContent::firstOrCreate(['foundation_id' => $request->user()->foundation_id], ['draft' => SiteContentRules::defaults()]);
    }

    /** Content plus the addresses of its photos, for drawing the page. */
    public static function withImages(array $content, int $foundationId): array
    {
        $ids = array_filter([...array_values($content['images'] ?? []), ...array_column($content['projects'] ?? [], 'photo'), ...array_column($content['team'] ?? [], 'photo'), ...array_column($content['news'] ?? [], 'photo')]);

        return ['content' => $content, 'images' => SiteImages::urls($ids, $foundationId)];
    }

    public function show(Request $request)
    {
        $row = $this->row($request);
        $fid = $request->user()->foundation_id;
        $draft = $row->draft ?: SiteContentRules::defaults();

        return ApiResponse::ok([
            'schema' => array_intersect_key(config('site'), array_flip(['texts', 'groups', 'singles', 'numbers', 'images', 'categories', 'lists'])),
            'unpublished_changes' => $row->version === 0 || $draft != $row->published,
            'version' => $row->version,
            'published_at' => $row->published_at?->toIso8601String(),
            'versions' => SiteVersion::where('foundation_id', $fid)->orderByDesc('version')->limit(10)->get(['version', 'published_by', 'created_at'])
                ->map(fn ($v) => ['version' => $v->version, 'at' => $v->created_at?->toIso8601String(), 'by' => $v->published_by])->all(),
        ] + self::withImages($draft, $fid));
    }

    public function save(Request $request)
    {
        $request->validate(['content' => ['required', 'array']]);
        $fid = $request->user()->foundation_id;
        [$clean, $errors] = SiteContentRules::clean($request->input('content'), $fid);
        if ($errors) {
            return ApiResponse::error('INVALID_CONTENT', 'Some fields need fixing.', 422, array_map(fn ($m) => [$m], $errors));
        }

        $row = $this->row($request);
        $row->update(['draft' => $clean, 'updated_by' => $request->user()->id]);

        return ApiResponse::updated(['unpublished_changes' => $clean != $row->published] + self::withImages($clean, $fid));
    }

    public function upload(Request $request)
    {
        $request->validate(['image' => ['required', 'file', 'max:5120']]);
        $r = SiteImages::store($request->file('image'), $request->user()->foundation_id, $request->user()->id);
        if (is_string($r)) {
            return ApiResponse::error('UNSAFE_FILE', $r, 422, ['image' => [$r]]);
        }
        Audit::record('site.photo_uploaded', $request, 'site_image', $r->id);

        return ApiResponse::created(['id' => $r->id, 'url' => $r->url(), 'width' => $r->width, 'height' => $r->height]);
    }

    public function publish(Request $request)
    {
        $row = $this->row($request);
        $fid = $request->user()->foundation_id;
        $draft = $row->draft ?: SiteContentRules::defaults();
        [$clean, $errors] = SiteContentRules::clean($draft, $fid);
        if ($errors) {
            return ApiResponse::error('INVALID_CONTENT', 'The draft has problems. Open the Website page and fix them first.', 422, array_map(fn ($m) => [$m], $errors));
        }

        DB::transaction(function () use ($row, $clean, $request, $fid) {
            $version = $row->version + 1;
            SiteVersion::create(['foundation_id' => $fid, 'version' => $version, 'content' => $clean, 'published_by' => $request->user()->id]);
            $row->update(['published' => $clean, 'draft' => $clean, 'version' => $version, 'published_at' => now(), 'published_by' => $request->user()->id]);
            SiteVersion::where('foundation_id', $fid)->where('version', '<=', $version - 20)->delete();
        });
        Audit::record('site.published', $request, 'site', $row->id, ['version' => $row->version]);

        return ApiResponse::ok(['version' => $row->version, 'published_at' => $row->published_at->toIso8601String()]);
    }

    /** Brings an earlier published version back into the DRAFT (nothing changes for visitors until it is published). */
    public function revert(Request $request, int $version)
    {
        $fid = $request->user()->foundation_id;
        $v = SiteVersion::where('foundation_id', $fid)->where('version', $version)->first();
        if (! $v) {
            return ApiResponse::notFound('No such version.');
        }
        $row = $this->row($request);
        $row->update(['draft' => $v->content, 'updated_by' => $request->user()->id]);
        Audit::record('site.reverted', $request, 'site', $row->id, ['to' => $version]);

        return ApiResponse::updated(['unpublished_changes' => $v->content != $row->published] + self::withImages($v->content, $fid));
    }
}
