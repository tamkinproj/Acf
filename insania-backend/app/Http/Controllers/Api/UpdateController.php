<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Updates\UpdateException;
use App\Updates\UpdateService;
use Illuminate\Http\Request;

/** System Admin only. Upload a signed patch, see what it would change, apply it, undo it. */
class UpdateController extends Controller
{
    public function __construct(private UpdateService $updates)
    {
    }

    public function index()
    {
        $history = array_reverse($this->updates->history());
        $latest = $this->updates->latestApplied();

        return ApiResponse::ok([
            'build' => $this->updates->currentBuild(),
            'key_fingerprint' => $this->updates->keyFingerprint(),
            'website_folder_found' => (bool) config('updates.public_path'),
            'undo_id' => $latest['id'] ?? null,
            'history' => array_slice($history, 0, 20),
        ]);
    }

    public function check(Request $request)
    {
        if ($big = \App\Support\UploadLimit::refused($request, 'patch')) {
            return ApiResponse::error('TOO_LARGE', $big, 413);
        }
        $request->validate(['patch' => ['required', 'file', 'max:61440'], 'signature' => ['required', 'file', 'max:16']]);

        try {
            $id = $this->updates->store($request->file('patch')->getRealPath(), (string) file_get_contents($request->file('signature')->getRealPath()));
            $result = $this->updates->inspect($id);
        } catch (UpdateException $e) {
            if (isset($id)) {
                $this->updates->discard($id);
            }
            Audit::record('update.rejected', $request, null, null, ['reason' => substr($e->getMessage(), 0, 120)]);

            return ApiResponse::error('UPDATE_REJECTED', $e->getMessage(), 422);
        }

        return ApiResponse::ok($result['summary']);
    }

    /** Upload and update in one step: check the signature and the files, back up, apply. Undo stays available. */
    public function auto(Request $request)
    {
        if ($big = \App\Support\UploadLimit::refused($request, 'patch')) {
            return ApiResponse::error('TOO_LARGE', $big, 413);
        }
        $request->validate(['patch' => ['required', 'file', 'max:61440'], 'signature' => ['nullable', 'file', 'max:16']]);

        try {
            $sig = $request->hasFile('signature') ? (string) file_get_contents($request->file('signature')->getRealPath()) : null;
            $id = $this->updates->storeUpload($request->file('patch')->getRealPath(), $sig);
            $this->updates->inspect($id);
            $entry = $this->updates->apply($id, $request->user()->id);
        } catch (UpdateException $e) {
            if (isset($id)) {
                $this->updates->discard($id);
            }
            Audit::record('update.rejected', $request, null, null, ['reason' => substr($e->getMessage(), 0, 120)]);

            return ApiResponse::error(str_contains($e->getMessage(), 'undone automatically') ? 'UPDATE_FAILED' : 'UPDATE_REJECTED', $e->getMessage(), 422);
        }
        Audit::record('update.applied', $request, null, null, ['build' => $entry['build'], 'from' => $entry['from_build']]);

        return ApiResponse::ok($entry);
    }

    public function apply(Request $request, string $id)
    {
        try {
            $entry = $this->updates->apply($id, $request->user()->id);
        } catch (UpdateException $e) {
            Audit::record('update.failed', $request, null, null, ['reason' => substr($e->getMessage(), 0, 120)]);

            return ApiResponse::error('UPDATE_FAILED', $e->getMessage(), 422);
        }
        Audit::record('update.applied', $request, null, null, ['build' => $entry['build'], 'from' => $entry['from_build']]);

        return ApiResponse::ok($entry);
    }

    public function undo(Request $request, string $entry)
    {
        try {
            $r = $this->updates->undo($entry, $request->user()->id);
        } catch (UpdateException $e) {
            return ApiResponse::error('UNDO_FAILED', $e->getMessage(), 422);
        }
        Audit::record('update.undone', $request, null, null, ['restored' => $r['restored_build']]);

        return ApiResponse::ok($r);
    }
}
