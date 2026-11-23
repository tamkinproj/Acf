<?php

namespace App\Support\Pdf;

/**
 * Fills the fields of a PDF form and nothing else. Each value is written into its field together with the picture
 * a viewer shows for it, drawn with the fonts already inside the form. The fields stay editable, the form's scripts
 * stay, and the page itself (its design, pictures and text) is copied unchanged.
 */
final class Form
{
    private const RICH = 1 << 25;

    private const MULTILINE = 1 << 12;

    /** @var array<string, array{num:int, widgets:int[], ft:?string, ff:int, da:?string, q:int}> */
    private array $fields = [];

    /** @var array<string, array> fonts drawn with, by their name in the form */
    private array $fonts = [];

    private array $touched = [];

    private Dict $dr;

    public function __construct(public Document $doc)
    {
        $af = $doc->resolve($doc->root()->get('AcroForm'));
        if (! $af instanceof Dict) {
            throw new \RuntimeException('This PDF has no form fields.');
        }
        $dr = $doc->resolve($af->get('DR'));
        $this->dr = $dr instanceof Dict ? $dr : new Dict();
        $daDefault = $af->get('DA') instanceof Str ? $af->get('DA')->v : null;
        foreach ((array) $af->get('Fields') as $r) {
            $this->walk($r, '', ['ft' => null, 'ff' => 0, 'da' => $daDefault, 'q' => (int) $af->get('Q')], 0);
        }
    }

    private function walk(mixed $ref, string $prefix, array $inh, int $depth): void
    {
        if (! $ref instanceof Ref || $depth > 20) {
            return;
        }
        $o = $this->doc->get($ref->num);
        if (! $o instanceof Dict) {
            return;
        }
        $t = $o->get('T') instanceof Str ? $o->get('T')->utf8() : null;
        $name = $t === null ? $prefix : ($prefix === '' ? $t : $prefix.'.'.$t);
        if ($o->get('FT') instanceof Name) {
            $inh['ft'] = $o->get('FT')->v;
        }
        if (is_int($o->get('Ff'))) {
            $inh['ff'] = $o->get('Ff');
        }
        if ($o->get('DA') instanceof Str) {
            $inh['da'] = $o->get('DA')->v;
        }
        if (is_int($o->get('Q'))) {
            $inh['q'] = $o->get('Q');
        }
        $kids = $this->doc->resolve($o->get('Kids'));
        $fieldKids = [];
        $widgets = [];
        foreach (is_array($kids) ? $kids : [] as $k) {
            $kd = $this->doc->resolve($k);
            if ($k instanceof Ref && $kd instanceof Dict) {
                if ($kd->has('T')) {
                    $fieldKids[] = $k;
                } else {
                    $widgets[] = $k->num;
                }
            }
        }
        foreach ($fieldKids as $k) {
            $this->walk($k, $name, $inh, $depth + 1);
        }
        if ($t === null) {
            return;
        }
        if ($o->name('Subtype') === 'Widget') {
            $widgets[] = $ref->num;
        }
        if ($widgets || ! $fieldKids) {
            $this->fields[$name] = ['num' => $ref->num, 'widgets' => $widgets] + $inh;
        }
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->fields);
    }

    public function has(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function type(string $name): ?string
    {
        return $this->fields[$name]['ft'] ?? null;
    }

    /** Choices of a list: [[value saved, text shown], ...] */
    public function options(string $name): array
    {
        if (! isset($this->fields[$name])) {
            return [];
        }
        $o = $this->doc->get($this->fields[$name]['num']);
        $opt = $this->doc->resolve($o->get('Opt'));
        $out = [];
        foreach (is_array($opt) ? $opt : [] as $x) {
            $x = $this->doc->resolve($x);
            if (is_array($x) && count($x) >= 2) {
                $out[] = [$this->text($x[0]), $this->text($x[1])];
            } else {
                $t = $this->text($x);
                $out[] = [$t, $t];
            }
        }

        return $out;
    }

    /** The value the template holds now (to check a form before it is used). */
    public function value(string $name): ?string
    {
        if (! isset($this->fields[$name])) {
            return null;
        }
        $v = $this->doc->resolve($this->doc->get($this->fields[$name]['num'])->get('V'));

        return $v instanceof Str ? $v->utf8() : ($v instanceof Name ? $v->v : null);
    }

    private function text(mixed $v): string
    {
        $v = $this->doc->resolve($v);

        return $v instanceof Str ? $v->utf8() : ($v instanceof Name ? $v->v : (is_scalar($v) ? (string) $v : ''));
    }

    private function edit(int $num, callable $fn): void
    {
        $o = Dict::copy($this->doc->get($num));
        if (! $o instanceof Dict) {
            return;
        }
        $fn($o);
        $this->doc->set($num, $o);
    }

    /** A text field. An empty value empties the field. */
    public function setText(string $name, ?string $value): void
    {
        $f = $this->fields[$name] ?? null;
        if (! $f) {
            return;
        }
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        $this->touched[$name] = true;
        $rich = ($f['ff'] & self::RICH) !== 0;
        $this->edit($f['num'], function (Dict $o) use ($value, $rich) {
            $o->set('V', $value === '' ? null : Str::text($value));
            $o->set('RV', $rich && $value !== '' ? new Str($this->richValue($o, $value)) : null);
        });
        foreach ($f['widgets'] as $w) {
            $this->appearance($w, $f, $value);
        }
    }

    /** A drop-down: $value is the saved value of one of its choices (or null to leave it empty). */
    public function setChoice(string $name, ?string $value): void
    {
        $f = $this->fields[$name] ?? null;
        if (! $f) {
            return;
        }
        $this->touched[$name] = true;
        $shown = '';
        $index = null;
        foreach ($this->options($name) as $i => [$exp, $disp]) {
            if ($value !== null && $value !== '' && $exp === $value) {
                [$shown, $index] = [$disp, $i];
                break;
            }
        }
        $this->edit($f['num'], function (Dict $o) use ($value, $index) {
            $o->set('V', $index === null ? null : Str::text((string) $value));
            $o->set('I', $index === null ? null : [$index]);
        });
        foreach ($f['widgets'] as $w) {
            $this->appearance($w, $f, $shown);
        }
    }

    /** Shows or hides a field (what the form's own scripts do with display.visible / display.hidden). */
    public function setHidden(string $name, bool $hidden): void
    {
        foreach ($this->fields[$name]['widgets'] ?? [] as $w) {
            $this->edit($w, function (Dict $o) use ($hidden) {
                $f = is_int($o->get('F')) ? $o->get('F') : 0;
                $o->set('F', $hidden ? ($f | 2) : ($f & ~2));
            });
        }
    }

    /** The picture of an image button (the orphan's photo). $jpeg: the photo as JPEG bytes, or null for none. */
    public function setImage(string $name, ?string $jpeg): void
    {
        $f = $this->fields[$name] ?? null;
        if (! $f) {
            return;
        }
        $this->touched[$name] = true;
        $info = $jpeg ? @getimagesizefromstring($jpeg) : null;
        $icon = null;
        if ($info && ($info[2] ?? 0) === IMAGETYPE_JPEG) {
            [$iw, $ih] = [$info[0], $info[1]];
            $cs = ($info['channels'] ?? 3) === 1 ? 'DeviceGray' : (($info['channels'] ?? 3) === 4 ? 'DeviceCMYK' : 'DeviceRGB');
            $img = $this->doc->add(new Stream(new Dict(['Type' => new Name('XObject'), 'Subtype' => new Name('Image'), 'Width' => $iw, 'Height' => $ih,
                'ColorSpace' => new Name($cs), 'BitsPerComponent' => 8, 'Filter' => new Name('DCTDecode')] + ($cs === 'DeviceCMYK' ? ['Decode' => [1, 0, 1, 0, 1, 0, 1, 0]] : [])), $jpeg));
            $icon = [$this->doc->add(Stream::make(['Type' => new Name('XObject'), 'Subtype' => new Name('Form'), 'BBox' => [0, 0, $iw, $ih],
                'Resources' => new Dict(['XObject' => new Dict(['Img' => $img])])], "q {$iw} 0 0 {$ih} 0 0 cm /Img Do Q", false)), $iw, $ih];
        }
        foreach ($f['widgets'] as $w) {
            $wd = $this->doc->get($w);
            [$bw, $bh] = $this->size($wd);
            $body = $this->frame($wd, $bw, $bh);
            $res = [];
            if ($icon) {
                [$ref, $iw, $ih] = $icon;
                $s = min(($bw - 4) / $iw, ($bh - 4) / $ih);
                $tx = 2 + (($bw - 4) - $iw * $s) / 2;
                $ty = 2 + (($bh - 4) - $ih * $s) / 2;
                $body .= sprintf("q 1 1 %s %s re W n q 2 2 %s %s re W n %s 0 0 %s %s %s cm /FRM Do Q Q\n", self::n($bw - 2), self::n($bh - 2), self::n($bw - 4), self::n($bh - 4), self::n($s), self::n($s), self::n($tx), self::n($ty));
                $res = ['XObject' => new Dict(['FRM' => $ref])];
            }
            $ap = $this->doc->add(Stream::make(['Type' => new Name('XObject'), 'Subtype' => new Name('Form'), 'FormType' => 1, 'BBox' => [0, 0, self::f($bw), self::f($bh)],
                'Matrix' => [1, 0, 0, 1, 0, 0], 'Resources' => new Dict($res)], $body));
            $this->edit($w, function (Dict $o) use ($ap, $icon) {
                $mk = $o->get('MK') instanceof Dict ? $o->get('MK') : new Dict();
                $mk->set('I', $icon ? $icon[0] : null);
                $o->set('MK', $mk);
                $o->set('AP', new Dict(['N' => $ap]));
            });
        }
    }

    /** Every text field and list not filled in is emptied, so nothing from the copy the form came from stays. */
    public function clearRest(): void
    {
        foreach ($this->fields as $name => $f) {
            if (isset($this->touched[$name])) {
                continue;
            }
            if ($f['ft'] === 'Tx') {
                $this->setText($name, '');
            } elseif ($f['ft'] === 'Ch') {
                $this->setChoice($name, null);
            }
        }
    }

    /** The finished PDF. */
    public function save(): string
    {
        $this->writeFonts();

        return $this->doc->save();
    }

    // ---------------------------------------------------------------- drawing a field's value

    private function size(Dict $w): array
    {
        $r = array_map(fn ($x) => (float) $this->doc->resolve($x), (array) $this->doc->resolve($w->get('Rect')) + [0, 0, 0, 0]);

        return [abs($r[2] - $r[0]), abs($r[3] - $r[1])];
    }

    /** Background and border the widget asks for (its /MK), like a viewer draws them. */
    private function frame(Dict $w, float $bw, float $bh): string
    {
        $mk = $this->doc->resolve($w->get('MK'));
        $out = '';
        if ($mk instanceof Dict) {
            $bg = $this->color($this->doc->resolve($mk->get('BG')), false);
            if ($bg !== '') {
                $out .= "q {$bg} 0 0 ".self::n($bw).' '.self::n($bh)." re f Q\n";
            }
            $bc = $this->color($this->doc->resolve($mk->get('BC')), true);
            $bs = $this->doc->resolve($w->get('BS'));
            $lw = $bs instanceof Dict && is_numeric($bs->get('W')) ? (float) $bs->get('W') : 1.0;
            if ($bc !== '' && $lw > 0) {
                $h = $lw / 2;
                $out .= "q {$bc} ".self::n($lw).' w '.self::n($h).' '.self::n($h).' '.self::n($bw - $lw).' '.self::n($bh - $lw)." re S Q\n";
            }
        }

        return $out;
    }

    private function color(mixed $a, bool $stroke): string
    {
        if (! is_array($a)) {
            return '';
        }
        $v = implode(' ', array_map(fn ($x) => self::n((float) $x), $a));

        return match (count($a)) {
            1 => $v.($stroke ? ' G' : ' g'),
            3 => $v.($stroke ? ' RG' : ' rg'),
            4 => $v.($stroke ? ' K' : ' k'),
            default => '',
        };
    }

    private function appearance(int $wnum, array $f, string $value): void
    {
        $w = $this->doc->get($wnum);
        if (! $w instanceof Dict) {
            return;
        }
        [$bw, $bh] = $this->size($w);
        $da = is_string($f['da']) ? $f['da'] : '/Helv 0 Tf 0 g';
        $wda = $w->get('DA') instanceof Str ? $w->get('DA')->v : $da;
        [$fontName, $size, $colorOp] = $this->parseDa($wda);
        $q = is_int($w->get('Q')) ? $w->get('Q') : $f['q'];
        $body = $this->frame($w, $bw, $bh);
        $res = [];
        if ($value !== '') {
            $pad = 2.0;
            $multi = ($f['ff'] & self::MULTILINE) !== 0;
            $font = $this->font($fontName, $value);
            $lf = $font['lineFactor'];
            $auto = $size <= 0;
            $sz = $auto ? max(4.0, min(14.0, ($bh - 2.6) / $lf)) : $size;
            $width = $bw - 2 * $pad;
            $paras = explode("\n", $value);
            $tall = $bh >= 2.2 * $sz * 1.15;   // room for more than one line: written from the top, like the form does
            if (! $multi && ! $tall) {   // one line only: make the letters smaller until it fits
                $flat = str_replace("\n", ' ', $value);
                $sz = max(4.0, min($sz, $width * 1000 / max(1, $this->measure($font, $flat))));
                $lines = [$flat];
            } else {
                $multi = true;
                $multi = true;
                do {
                    $lines = $this->wrap($font, $paras, $width * 1000 / $sz);
                    $fits = count($lines) * $sz * 1.15 <= $bh - 2 * $pad + 0.01;
                    if (! $fits) {
                        $sz -= 0.5;
                    }
                } while (! $fits && $sz > 5);
            }
            $text = '';
            $asc = $font['ascent'] * $sz / 1000;
            $desc = -$font['descent'] * $sz / 1000;
            foreach ($lines as $i => $line) {
                $runs = $this->runs($font, $line);
                $tw = 0;
                foreach ($runs as $r) {
                    $tw += $r['w'] * $sz / 1000;
                }
                $x = match ($q) {
                    1 => ($bw - $tw) / 2,
                    2 => $bw - $pad - $tw,
                    default => $pad,
                };
                $y = $multi ? $bh - $pad - $asc - $i * $sz * 1.15 : ($bh - ($asc + $desc)) / 2 + $desc;
                $text .= '1 0 0 1 '.self::n($x).' '.self::n($y)." Tm\n";
                foreach ($runs as $r) {
                    $res[$r['res']] = $r['ref'];
                    $text .= '/'.$r['res'].' '.self::n($sz)." Tf\n".$r['tj']."\n";
                }
            }
            $body .= "/Tx BMC\nq\n1 1 ".self::n($bw - 2).' '.self::n($bh - 2)." re W n\nBT\n{$colorOp}\n{$text}ET\nQ\nEMC\n";
        }
        $ap = $this->doc->add(Stream::make(['Type' => new Name('XObject'), 'Subtype' => new Name('Form'), 'FormType' => 1, 'BBox' => [0, 0, self::f($bw), self::f($bh)],
            'Matrix' => [1, 0, 0, 1, 0, 0], 'Resources' => new Dict($res ? ['Font' => new Dict($res)] : [])], $body));
        $this->edit($wnum, function (Dict $o) use ($ap) {
            $o->set('AP', new Dict(['N' => $ap]));
        });
    }

    private function parseDa(string $da): array
    {
        $font = 'Helv';
        $size = 0.0;
        if (preg_match('/\/([^\s\/]+)\s+([\d.]+)\s+Tf/', $da, $m)) {
            [$font, $size] = [$m[1], (float) $m[2]];
        }
        $color = '0 g';
        if (preg_match('/((?:[\d.]+\s+){1,4})(g|rg|k)\b/', $da, $m)) {
            $color = trim($m[1]).' '.$m[2];
        }

        return [$font, $size, $color];
    }

    /** Splits paragraphs into lines that fit (widths in thousandths of the font size). */
    private function wrap(array $font, array $paras, float $max): array
    {
        $lines = [];
        foreach ($paras as $p) {
            $words = preg_split('/ +/u', trim($p));
            $cur = '';
            foreach ($words as $word) {
                $try = $cur === '' ? $word : $cur.' '.$word;
                if ($cur !== '' && $this->measure($font, $try) > $max) {
                    $lines[] = $cur;
                    $cur = $word;
                } else {
                    $cur = $try;
                }
            }
            $lines[] = $cur;
        }

        return $lines;
    }

    private function measure(array $font, string $line): float
    {
        $w = 0;
        foreach ($this->runs($font, $line) as $r) {
            $w += $r['w'];
        }

        return $w;
    }

    /**
     * The line drawn: pieces of text in one font each, as TJ operators with widths.
     *
     * @return array<int, array{res:string, ref:Ref, tj:string, w:float}>
     */
    private function runs(array $font, string $line): array
    {
        $chain = $this->fallbacks($font);
        $has = function (int $cp) use ($chain) {
            foreach ($chain as $k) {
                if ($this->fonts[$k]['kind'] === 'cid' && isset($this->fonts[$k]['tt']->cmap[$cp])) {
                    return true;
                }
            }

            return false;
        };
        $clusters = Arabic::visual($line, $has, false);
        $runs = [];
        $cur = null;
        foreach ($clusters as $cl) {
            // the font for this cluster: the first one that has its letter
            $key = null;
            foreach ($chain as $k) {
                if ($this->canDraw($k, $cl[0][0])) {
                    $key = $k;
                    break;
                }
            }
            if ($key === null) {
                continue;   // a sign no font in the form can draw (it is left out, like other viewers do)
            }
            $fd = &$this->fonts[$key];
            if (! $cur || $cur['key'] !== $key) {
                if ($cur) {
                    $runs[] = $cur;
                }
                $cur = ['key' => $key, 'parts' => [], 'w' => 0.0];
            }
            if ($fd['kind'] === 'simple') {
                $code = $cl[0][0];
                $cur['parts'][] = '('.strtr(chr($code), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']).')';
                $cur['w'] += $fd['widths'][$code] ?? 0;
                unset($fd);

                continue;
            }
            $tt = $fd['tt'];
            $base = $tt->cmap[$cl[0][0]];
            $fd['used'][$base] = $cl[0][1];
            $baseW = $tt->width($base);
            foreach (array_slice($cl, 1) as [$mcp, $mtext]) {   // marks: drawn first, centred over their letter
                $mg = $tt->cmap[$mcp] ?? null;
                if ($mg === null) {
                    continue;
                }
                $fd['used'][$mg] = $mtext;
                $bb = $tt->bbox($mg);
                $shift = $bb ? ($baseW - ($bb[2] - $bb[0]) * 1000 / $tt->upm) / 2 - $bb[0] * 1000 / $tt->upm : 0;
                $mw = $tt->width($mg);
                $cur['parts'][] = self::n(-$shift).' <'.sprintf('%04X', $mg).'> '.self::n($shift + $mw);
            }
            $cur['parts'][] = '<'.sprintf('%04X', $base).'>';
            $cur['w'] += $baseW;
            unset($fd);
        }
        unset($fd);
        if ($cur) {
            $runs[] = $cur;
        }
        $out = [];
        foreach ($runs as $r) {
            $one = $this->fonts[$r['key']];
            $out[] = ['res' => $one['res'], 'ref' => $one['ref'], 'tj' => '['.implode(' ', $r['parts']).'] TJ', 'w' => $r['w']];
        }

        return $out;
    }

    private function canDraw(string $key, int $cp): bool
    {
        $f = $this->fonts[$key];
        if ($f['kind'] === 'simple') {
            return $cp >= 0x20 && $cp < 0x7F && isset($f['widths'][$cp]);
        }

        return isset($f['tt']->cmap[$cp]);
    }

    /** The field's own font first, then the other fonts in the form that can draw Arabic and Latin text. */
    private function fallbacks(array $font): array
    {
        $chain = [$font['key']];
        $fonts = $this->doc->resolve($this->dr->get('Font'));
        foreach ($fonts instanceof Dict ? array_keys($fonts->v) : [] as $name) {
            $f = $this->load((string) $name);
            if ($f && $f['kind'] === 'cid' && ! in_array($f['key'], $chain, true)) {
                $chain[] = $f['key'];
            }
        }

        return $chain;
    }

    /** The font a field asks for, ready to draw with (a simple font only for plain English letters and digits). */
    private function font(string $name, string $value): array
    {
        $f = $this->load($name);
        $ascii = (bool) preg_match('/^[\x20-\x7e]*$/', $value);
        if ($f && ($f['kind'] === 'cid' || ($ascii && $f['kind'] === 'simple'))) {
            return $f;
        }
        $fonts = $this->doc->resolve($this->dr->get('Font'));
        foreach ($fonts instanceof Dict ? array_keys($fonts->v) : [] as $other) {
            $g = $this->load((string) $other);
            if ($g && $g['kind'] === 'cid') {
                return $g;
            }
        }
        throw new \RuntimeException('The form has no font that can write this text.');
    }

    private function load(string $name): ?array
    {
        if (array_key_exists('k:'.$name, $this->fonts)) {
            return $this->fonts['k:'.$name];
        }
        $this->fonts['k:'.$name] = null;
        $fonts = $this->doc->resolve($this->dr->get('Font'));
        $ref = $fonts instanceof Dict ? $fonts->get($name) : null;
        $fd = $this->doc->resolve($ref);
        if (! $fd instanceof Dict || ! $ref instanceof Ref) {
            return null;
        }
        $desc = $this->doc->resolve($fd->get('FontDescriptor'));
        $asc = $desc instanceof Dict && is_numeric($desc->get('Ascent')) ? (float) $desc->get('Ascent') : 800.0;
        $dsc = $desc instanceof Dict && is_numeric($desc->get('Descent')) ? (float) $desc->get('Descent') : -200.0;
        $file = $desc instanceof Dict ? $this->doc->resolve($desc->get('FontFile2')) : null;
        $key = 'k:'.$name;
        if ($fd->name('Subtype') === 'TrueType' && $file instanceof Stream) {
            try {
                $tt = TrueType::parse($file->decoded());
            } catch (\Throwable) {
                $tt = null;
            }
            if ($tt && count($tt->cmap) > 50) {
                $this->fonts[$key] = ['key' => $key, 'kind' => 'cid', 'tt' => $tt, 'desc' => $fd->get('FontDescriptor'), 'base' => $fd->get('BaseFont'),
                    'ref' => $this->doc->add(null), 'res' => 'U'.count($this->fonts), 'used' => [], 'ascent' => $asc, 'descent' => $dsc, 'lineFactor' => max(0.5, ($asc - $dsc) / 1000)];

                return $this->fonts[$key];
            }
        }
        $widths = $this->doc->resolve($fd->get('Widths'));
        if (is_array($widths) && is_int($fd->get('FirstChar'))) {
            $map = [];
            foreach ($widths as $i => $wv) {
                $map[$fd->get('FirstChar') + $i] = (float) $this->doc->resolve($wv);
            }
            $this->fonts[$key] = ['key' => $key, 'kind' => 'simple', 'widths' => $map, 'ref' => $ref, 'res' => 'S'.count($this->fonts), 'ascent' => $asc, 'descent' => $dsc, 'lineFactor' => max(0.5, ($asc - $dsc) / 1000)];

            return $this->fonts[$key];
        }

        return null;
    }

    /** Fonts used by the new field pictures: the form's own font file, with the glyphs looked up by number (Identity-H). */
    private function writeFonts(): void
    {
        foreach ($this->fonts as $f) {
            if (! $f || $f['kind'] !== 'cid') {
                continue;
            }
            ksort($f['used']);
            $w = [];
            $cmap = '';
            $count = 0;
            $chunks = [];
            foreach ($f['used'] as $gid => $text) {
                $w[] = $gid;
                $w[] = [round($f['tt']->width($gid), 2)];
                $u = strtoupper(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')));
                $chunks[] = sprintf('<%04X> <%s>', $gid, $u);
            }
            foreach (array_chunk($chunks, 100) as $c) {
                $cmap .= count($c)." beginbfchar\n".implode("\n", $c)."\nendbfchar\n";
            }
            $toUni = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n{$cmap}endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
            $base = $f['base'] instanceof Name ? $f['base'] : new Name('Font');
            $desc = Dict::copy($this->doc->resolve($f['desc']));
            $desc->set('CharSet', null);
            $cid = $this->doc->add(new Dict(['Type' => new Name('Font'), 'Subtype' => new Name('CIDFontType2'), 'BaseFont' => $base,
                'CIDSystemInfo' => new Dict(['Registry' => new Str('Adobe'), 'Ordering' => new Str('Identity'), 'Supplement' => 0]),
                'FontDescriptor' => $this->doc->add($desc), 'DW' => 1000, 'W' => $w, 'CIDToGIDMap' => new Name('Identity')]));
            $this->doc->set($f['ref']->num, new Dict(['Type' => new Name('Font'), 'Subtype' => new Name('Type0'), 'BaseFont' => $base,
                'Encoding' => new Name('Identity-H'), 'DescendantFonts' => [$cid], 'ToUnicode' => $this->doc->add(Stream::make([], $toUni))]));
        }
    }

    private function richValue(Dict $o, string $value): string
    {
        $ds = $o->get('DS') instanceof Str ? $o->get('DS')->utf8() : '';
        $family = preg_match('/font-family:\s*([^;]+)/', $ds, $m) ? trim($m[1]) : 'Helvetica';
        $rtl = Arabic::isRtl($value, false);
        $esc = function (string $s) {
            $out = '';
            foreach (Arabic::codepoints($s) as $c) {
                $out .= $c < 0x80 ? htmlspecialchars(chr($c), ENT_XML1) : '&#'.$c.';';
            }

            return $out;
        };
        $ps = '';
        foreach (explode("\n", $value) as $p) {
            $ps .= '<p dir="'.($rtl ? 'rtl' : 'ltr').'"><span style="text-align:'.($rtl ? 'right' : 'left').';font-style:normal;font-weight:normal;font-family:'.$esc($family).'">'.$esc($p).'</span></p>';
        }

        return '<?xml version="1.0"?><body xmlns="http://www.w3.org/1999/xhtml" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/" style="text-valign:top" xfa:APIVersion="Acroform:2.7.0.0" xfa:spec="2.1">'.$ps.'</body>';
    }

    /** A number for page content: at most three decimals. */
    public static function n(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.3F', $v), '0'), '.');

        return $s === '-0' || $s === '' ? '0' : $s;
    }

    private static function f(float $v): float|int
    {
        $r = round($v, 3);

        return $r == (int) $r ? (int) $r : $r;
    }
}
