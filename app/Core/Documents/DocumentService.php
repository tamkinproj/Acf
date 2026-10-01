<?php

namespace App\Core\Documents;

use App\Core\Audit\Auditor;
use App\Models\Aytam;
use App\Models\Document;
use App\Models\Program;
use App\Models\User;
use App\Sync\RejectChange;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Uploading, versioning and verifying documents. A document is never overwritten: a replacement is a new version in the
 * same slot (group), the old version stays in the history, and exactly one version is current.
 */
class DocumentService
{
    public function __construct(private DocumentStorage $storage, private Auditor $auditor) {}

    /**
     * @param  array{type:string,expires_on?:?string,notes?:?string}  $meta
     * @param  Document|null  $replaces  the current document this upload supersedes
     *
     * @throws RejectChange
     */
    public function upload(Program $program, UploadedFile $file, array $meta, ?User $actor, ?Aytam $aytam = null, ?string $registrationId = null, ?Document $replaces = null): Document
    {
        try {
            $stored = $this->storage->store($file, $program->foundation_id, $program->getKey(), imageOnly: $meta['type'] === 'photo');
        } catch (\InvalidArgumentException $e) {
            throw new RejectChange('invalid_file', $e->getMessage());
        }

        try {
            return DB::transaction(function () use ($program, $stored, $meta, $actor, $aytam, $registrationId, $replaces) {
                // A person has one current photo: a new one is a new version of the same slot.
                $replaces ??= $meta['type'] === 'photo' && $aytam
                    ? Document::query()->where('aytam_id', $aytam->getKey())->where('type', 'photo')->where('is_current', true)->first()
                    : null;

                $group = $replaces?->group_id ?? (string) Str::uuid7();
                $version = $replaces ? 1 + (int) Document::query()->where('group_id', $group)->max('doc_version') : 1;
                $replaces?->update(['is_current' => false]);

                $doc = new Document;
                $doc->forceFill([
                    'program_id' => $program->getKey(), 'aytam_id' => $aytam?->getKey() ?? $replaces?->aytam_id, 'registration_id' => $registrationId ?? $replaces?->registration_id,
                    'group_id' => $group, 'doc_version' => $version, 'is_current' => true,
                    'type' => $meta['type'], 'original_name' => $stored['original_name'], 'storage_path' => $stored['path'],
                    'mime' => $stored['mime'], 'size' => $stored['size'], 'sha256' => $stored['sha256'],
                    'verification_status' => Document::PENDING, 'expires_on' => $meta['expires_on'] ?? null, 'notes' => $meta['notes'] ?? null,
                ])->save();

                return $doc;
            });
        } catch (\Throwable $e) {
            $this->storage->delete($stored['path']);   // never leave an orphan file behind a failed save
            throw $e;
        }
    }

    /**
     * A registration's document joins the child's record on approval. If the child already has a current document of the
     * same single-slot kind (a photo), it becomes the next version of that slot instead of a second "current" photo.
     */
    public function attachToAytam(Document $doc, Aytam $aytam): void
    {
        DB::transaction(function () use ($doc, $aytam) {
            $current = $doc->type === 'photo'
                ? Document::query()->where('aytam_id', $aytam->getKey())->where('type', 'photo')->where('is_current', true)->where('id', '!=', $doc->getKey())->first()
                : null;
            if ($current) {
                $current->update(['is_current' => false]);
                $doc->group_id = $current->group_id;
                $doc->doc_version = 1 + (int) Document::query()->where('group_id', $current->group_id)->max('doc_version');
            }
            $doc->aytam_id = $aytam->getKey();
            $doc->save();
        });
    }

    /** @throws RejectChange */
    public function decide(Document $document, string $decision, User $actor, ?string $reason = null, ?string $expiresOn = null): Document
    {
        if (! $document->is_current) {
            throw new RejectChange('superseded', 'Only the current version of a document can be verified.');
        }
        if ($decision === Document::REJECTED && trim((string) $reason) === '') {
            throw new RejectChange('reason_required', 'Say why this document is rejected.');
        }
        $document->forceFill([
            'verification_status' => $decision, 'verified_by' => $actor->getKey(), 'verified_at' => now(),
            'rejection_reason' => $decision === Document::REJECTED ? mb_substr(trim((string) $reason), 0, 500) : null,
            'expires_on' => $expiresOn ?? $document->expires_on,
        ])->save();

        $this->auditor->record('document.'.$decision, "Document {$document->type} v{$document->doc_version} {$decision}".($reason ? " ({$reason})" : ''), 'documents', $document->getKey(), null, ['status' => $decision]);

        return $document;
    }
}
