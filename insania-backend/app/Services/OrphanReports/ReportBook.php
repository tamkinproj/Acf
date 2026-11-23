<?php

namespace App\Services\OrphanReports;

use App\Models\Child;
use App\Models\OrphanReport;
use Illuminate\Support\Collection;

/**
 * One year of reports in the partner the request works in. Saved reports keep their texts; for an orphan with no
 * saved text yet, one is written, going through the orphans in a fixed order so that the same orphan always gets
 * the same text (in the profile, a single download and a batch) and no two orphans get the same one.
 */
final class ReportBook
{
    /** @var Collection<int, OrphanReport> */
    public Collection $reports;

    private ReportTexts $texts;

    private ?Collection $children = null;

    private array $written = [];

    private int $cursor = 0;

    public function __construct(public int $year, public array $settings)
    {
        $this->reports = OrphanReport::query()->where('year', $year)->get()->keyBy('child_id');
        $this->texts = ReportTexts::forYear($year);
    }

    /** The orphans a report is made for: active and pending ones (an ended sponsorship needs no report). */
    public function children(): Collection
    {
        return $this->children ??= Child::query()->with(['guardian', 'sponsorship.sponsor'])->whereIn('status', ['active', 'pending'])->orderBy('id')->get();
    }

    public function report(Child $c): ?OrphanReport
    {
        return $this->reports->get($c->id);
    }

    /** The three texts for an orphan: saved ones, or written now. Also the variant codes. */
    public function textsFor(Child $c): array
    {
        $r = $this->report($c);
        $need = array_filter(ReportTexts::KINDS, fn ($k) => ! trim((string) $r?->{$k}));
        if (! $need) {
            return ['changes' => $r->changes, 'comment' => $r->comment, 'message' => $r->message, 'codes' => (string) $r->codes, 'written' => []];
        }
        if (! isset($this->written[$c->id])) {
            // write for every orphan before this one first, so the result does not depend on who is asked about first
            $list = $this->children()->values();
            while ($this->cursor < $list->count() && $list[$this->cursor]->id < $c->id) {
                $this->write($list[$this->cursor]);
                $this->cursor++;
            }
            $this->write($c);
        }
        $out = ['codes' => '', 'written' => array_values($need)];
        $codes = [];
        foreach (ReportTexts::KINDS as $k) {
            if (in_array($k, $need, true)) {
                [$out[$k], $code] = $this->written[$c->id][$k];
                $codes[] = $code;
            } else {
                $out[$k] = $r->{$k};
            }
        }
        $out['codes'] = implode('|', $codes);

        return $out;
    }

    private function write(Child $c): void
    {
        if (isset($this->written[$c->id])) {
            return;
        }
        $r = $this->report($c);
        $input = DarAlBerReport::phraseInput(DarAlBerReport::facts($c, $r, $this->year, $this->settings));
        foreach (ReportTexts::KINDS as $k) {
            if (! trim((string) $r?->{$k})) {
                [$text, $code] = $this->texts->make($k, $input, $c->id, $this->year);
                $this->written[$c->id][$k] = [$text, $code];
            }
        }
    }

    /** Everything that goes on this orphan's form. */
    public function facts(Child $c): array
    {
        return DarAlBerReport::facts($c, $this->report($c), $this->year, $this->settings, $this->textsFor($c));
    }
}
