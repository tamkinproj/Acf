<?php

namespace App\Modules\Aytam;

use App\Models\Aytam;
use App\Models\Program;

/**
 * Finds records that might be the same child. It only ever SUGGESTS: nothing is merged or discarded automatically, a
 * person decides (use the existing record, create a new one, or look closer).
 *
 *   exact     same identifier, or the same name and the same date of birth
 *   probable  very similar name and the same date of birth, or the same name with no date to compare
 *   possible  similar name and the same date of birth, or a near-identical name with no conflicting date
 */
class DuplicateDetector
{
    public const EXACT = 'exact';
    public const PROBABLE = 'probable';
    public const POSSIBLE = 'possible';

    private const MAX_CANDIDATES = 300;

    /**
     * @param  array<string,mixed>  $candidate  canonical values: first_name, middle_name, last_name, arabic_name, date_of_birth, legacy_ref
     * @return list<array{aytam_id:string,aytam_code:string,name:string,date_of_birth:?string,status:string,level:string,score:float,reasons:list<string>}>
     */
    public function find(Program $program, array $candidate, ?string $ignoreAytamId = null, int $limit = 5): array
    {
        $norm = NameNormalizer::name($candidate['first_name'] ?? null, $candidate['middle_name'] ?? null, $candidate['last_name'] ?? null);
        $normArabic = NameNormalizer::name($candidate['arabic_name'] ?? null);
        $dob = $this->date($candidate['date_of_birth'] ?? null);
        $ref = trim((string) ($candidate['legacy_ref'] ?? ''));
        if ($norm === '' && $normArabic === '' && $ref === '') {
            return [];
        }
        $tokens = array_merge(NameNormalizer::tokens($candidate['first_name'] ?? null, $candidate['last_name'] ?? null), NameNormalizer::tokens($candidate['arabic_name'] ?? null));

        $pool = Aytam::query()->where('program_id', $program->getKey())
            ->when($ignoreAytamId, fn ($q) => $q->where('id', '!=', $ignoreAytamId))
            ->where(function ($q) use ($dob, $ref, $tokens) {
                $q->whereRaw('1 = 0');
                if ($dob) {
                    $q->orWhere('date_of_birth', $dob);
                }
                if ($ref !== '') {
                    $q->orWhere('legacy_ref', $ref)->orWhere('aytam_code', $ref);
                }
                foreach (array_slice($tokens, 0, 4) as $token) {
                    if (mb_strlen($token) >= 3) {
                        $q->orWhere('normalized_name', 'like', '%'.addcslashes($token, '%_\\').'%');
                    }
                }
            })
            ->limit(self::MAX_CANDIDATES)->get();

        $matches = [];
        foreach ($pool as $existing) {
            [$level, $score, $reasons] = $this->compare($existing, $norm, $normArabic, $dob, $ref);
            if ($level !== null) {
                $matches[] = [
                    'aytam_id' => $existing->getKey(), 'aytam_code' => $existing->aytam_code, 'name' => $existing->fullName(),
                    'date_of_birth' => $existing->date_of_birth?->toDateString(), 'status' => $existing->status,
                    'level' => $level, 'score' => round($score, 2), 'reasons' => $reasons,
                ];
            }
        }
        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }

    /** @return array{0:?string,1:float,2:list<string>} */
    private function compare(Aytam $existing, string $norm, string $normArabic, ?string $dob, string $ref): array
    {
        $reasons = [];
        if ($ref !== '' && ($existing->legacy_ref === $ref || $existing->aytam_code === $ref)) {
            return [self::EXACT, 1.0, ['Same identifier ('.$ref.')']];
        }

        $name = max(
            $norm !== '' ? NameNormalizer::similarity($norm, (string) $existing->normalized_name) : 0.0,
            $normArabic !== '' && $existing->arabic_name ? NameNormalizer::similarity($normArabic, NameNormalizer::name($existing->arabic_name)) : 0.0,
        );
        $theirDob = $existing->date_of_birth?->toDateString();
        $dobKnown = $dob !== null && $theirDob !== null;
        $sameDob = $dobKnown && $dob === $theirDob;
        $conflictingDob = $dobKnown && ! $sameDob;

        if ($name >= 0.98) {
            $reasons[] = 'Same name';
        } elseif ($name >= 0.75) {
            $reasons[] = 'Similar name ('.(int) round($name * 100).'%)';
        }
        if ($sameDob) {
            $reasons[] = 'Same date of birth';
        } elseif ($conflictingDob) {
            $reasons[] = 'Different date of birth';
        }

        $level = null;
        if ($name >= 0.98 && $sameDob) {
            $level = self::EXACT;
        } elseif (($name >= 0.85 && $sameDob) || ($name >= 0.98 && ! $dobKnown)) {
            $level = self::PROBABLE;
        } elseif (($name >= 0.75 && $sameDob) || ($name >= 0.9 && ! $conflictingDob)) {
            $level = self::POSSIBLE;
        }

        return [$level, $name * ($sameDob ? 1.0 : ($dobKnown ? 0.6 : 0.85)), $reasons];
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
