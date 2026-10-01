<?php

namespace App\Modules\Aytam;

use App\Programs\ProgramModule;

/** Orphan care: the first complete program. */
final class AytamModule extends ProgramModule
{
    public const KEY = 'aytam';
    public const MUSHRIF = 'aytam_mushrif';
    public const FIELD_WORKER = 'aytam_field_worker';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Aytam';
    }

    public function description(): string
    {
        return 'Orphan records, families, guardians, documents, registration and review.';
    }

    public function categories(): array
    {
        return ['aytam'];
    }

    public function permissions(): array
    {
        return [
            'aytam.view' => ['label' => 'View Aytam records (assigned ones, unless "view all")', 'group' => 'Aytam'],
            'aytam.view_all' => ['label' => 'View every Aytam record, family and guardian', 'group' => 'Aytam'],
            'aytam.create' => ['label' => 'Create Aytam records', 'group' => 'Aytam'],
            'aytam.update' => ['label' => 'Edit Aytam records, families and guardians', 'group' => 'Aytam'],
            'aytam.review' => ['label' => 'Review registrations and approve or return records', 'group' => 'Aytam'],
            'aytam.assign' => ['label' => 'Assign field workers to Aytam records', 'group' => 'Aytam'],
            'aytam.configure' => ['label' => 'Configure Aytam requirements and partner relationships', 'group' => 'Aytam'],
            'aytam.import' => ['label' => 'Import existing Aytam data', 'group' => 'Aytam'],
            'documents.view' => ['label' => 'View documents', 'group' => 'Documents'],
            'documents.upload' => ['label' => 'Upload documents and photos', 'group' => 'Documents'],
            'documents.verify' => ['label' => 'Verify or reject documents', 'group' => 'Documents'],
            'forms.view' => ['label' => 'View registration forms', 'group' => 'Forms'],
            'forms.create' => ['label' => 'Create registration forms', 'group' => 'Forms'],
            'forms.update' => ['label' => 'Edit registration forms', 'group' => 'Forms'],
            'forms.publish' => ['label' => 'Publish and unpublish registration forms', 'group' => 'Forms'],
        ];
    }

    public function roles(): array
    {
        return [
            self::MUSHRIF => [
                'name' => 'Aytam Mushrif',
                'description' => 'Supervises the Aytam program: records, registration, review, documents, forms and partners.',
                'permissions' => [
                    'aytam.view', 'aytam.view_all', 'aytam.create', 'aytam.update', 'aytam.review', 'aytam.assign',
                    'aytam.configure', 'aytam.import', 'documents.view', 'documents.upload', 'documents.verify',
                    'forms.view', 'forms.create', 'forms.update', 'forms.publish',
                ],
            ],
            self::FIELD_WORKER => [
                'name' => 'Aytam Field Worker',
                'description' => 'Works on assigned Aytam records: adds information, uploads documents and photos.',
                'permissions' => ['aytam.view', 'aytam.update', 'documents.view', 'documents.upload'],
            ],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'code_prefix' => 'AYT',
            'required_documents' => ['photo', 'birth_certificate'],
        ];
    }
}
