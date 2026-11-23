<?php

namespace App\Services\OrphanReports;

use App\Models\Setting;
use App\Support\Pdf\Document;
use App\Support\Pdf\Form;
use Illuminate\Support\Facades\Storage;

/**
 * The partner's report form (the PDF they send), kept privately on the server. Every upload is kept; the newest is used.
 * Also the two things the report says about the foundation itself: its Arabic name and its country.
 */
final class ReportTemplates
{
    private const MAX_KEEP = 10;

    private static array $docs = [];

    private static function key(string $partner): string
    {
        return 'report_form.'.$partner;
    }

    /** @return array{versions: array, org_name: string, country: string} */
    public static function info(int $foundationId, string $partner): array
    {
        $raw = (array) json_decode((string) Setting::withoutGlobalScopes()->where('foundation_id', $foundationId)->where('key', self::key($partner))->value('value'), true);
        $nameAr = \App\Models\Foundation::query()->whereKey($foundationId)->value('name_ar');

        return [
            'versions' => array_values((array) ($raw['versions'] ?? [])),
            'org_name' => (string) (($raw['org_name'] ?? '') ?: ($nameAr ?: 'مؤسسة الانسانية الخيرية')),
            'country' => (string) (($raw['country'] ?? '') ?: 'PH'),
        ];
    }

    private static function save(int $foundationId, string $partner, array $info): void
    {
        Setting::withoutGlobalScopes()->updateOrCreate(['foundation_id' => $foundationId, 'key' => self::key($partner)], ['value' => json_encode($info, JSON_UNESCAPED_UNICODE)]);
    }

    public static function current(int $foundationId, string $partner): ?array
    {
        return self::info($foundationId, $partner)['versions'][0] ?? null;
    }

    /** The form, opened (once per request). */
    public static function document(int $foundationId, string $partner): ?Document
    {
        $cur = self::current($foundationId, $partner);
        if (! $cur) {
            return null;
        }
        $k = $foundationId.'|'.$cur['path'];
        if (! isset(self::$docs[$k])) {
            $bytes = Storage::disk('local')->get($cur['path']);
            if ($bytes === null) {
                return null;
            }
            self::$docs[$k] = Document::load($bytes);
        }

        return self::$docs[$k];
    }

    /** Checks a PDF is this partner's form and keeps it. Returns the saved version, or a plain-language reason. */
    public static function store(int $foundationId, string $partner, string $bytes, string $filename, int $userId): array|string
    {
        try {
            $doc = Document::load($bytes);
            $form = new Form($doc);
        } catch (\Throwable $e) {
            return 'This file could not be read as a PDF form: '.$e->getMessage();
        }
        $missing = array_values(array_filter(DarAlBerReport::REQUIRED, fn ($f) => ! $form->has($f)));
        if ($missing) {
            return 'This is not the Dar Al Ber report form (fields not found: '.implode(', ', array_slice($missing, 0, 4)).').';
        }
        $path = 'partner-forms/'.$foundationId.'/'.$partner.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.pdf';
        if (! Storage::disk('local')->put($path, $bytes)) {
            return 'The form could not be saved on the server.';
        }
        $info = self::info($foundationId, $partner);
        $version = ['path' => $path, 'name' => mb_substr($filename, 0, 120), 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
            'fields' => count($form->names()), 'uploaded_at' => now()->toIso8601String(), 'uploaded_by' => $userId];
        array_unshift($info['versions'], $version);
        foreach (array_slice($info['versions'], self::MAX_KEEP) as $old) {
            Storage::disk('local')->delete($old['path']);
        }
        $info['versions'] = array_slice($info['versions'], 0, self::MAX_KEEP);
        self::save($foundationId, $partner, $info);

        return $version;
    }

    public static function setDetails(int $foundationId, string $partner, string $orgName, string $country): void
    {
        $info = self::info($foundationId, $partner);
        $info['org_name'] = $orgName;
        $info['country'] = strtoupper($country);
        self::save($foundationId, $partner, $info);
    }
}
