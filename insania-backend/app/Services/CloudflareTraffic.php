<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cloudflare's own figures for the Security page: every visit to the website (the landing page too), countries,
 * most visited pages, busiest addresses, and what Cloudflare stopped and why.
 *
 * Three separate questions, so one that the Cloudflare plan or token does not allow does not hide the others: each part
 * carries Cloudflare's own message instead. Free plans keep a shorter history: a part that cannot go back that far is
 * asked again for the last 24 hours, and says so. Answers are kept for five minutes (Cloudflare limits how often we ask).
 */
final class CloudflareTraffic
{
    public const KEEP_SECONDS = 300;

    public static function overview(Cloudflare $cf, string $range, Carbon $from, bool $fresh = false): array
    {
        $key = 'cf-traffic:'.$cf->zone.':'.$range;
        if (! $fresh && ($hit = Cache::get($key))) {
            return $hit;
        }
        $until = now();
        $out = ['connected' => true, 'range' => $range, 'daily' => $range !== 'today', 'at' => $until->toIso8601String(), 'errors' => [], 'notes' => []];
        $out += self::totals($cf, $range, $from, $until, $out['errors']);
        $out += self::top($cf, $range, $from, $until, $out['errors'], $out['notes']);
        $out += self::stopped($cf, $range, $from, $until, $out['errors'], $out['notes']);
        Cache::put($key, $out, self::KEEP_SECONDS);

        return $out;
    }

    private static function t(Carbon $c): string
    {
        return $c->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** Requests, page views, visitors, threats, the chart and the countries. */
    private static function totals(Cloudflare $cf, string $range, Carbon $from, Carbon $until, array &$errors): array
    {
        $hours = $range === 'today';
        $q = 'query ($zone: String!, $d0: Date!, $d1: Date!'.($hours ? ', $since: Time!, $until: Time!' : '').') { viewer { zones(filter: {zoneTag: $zone}) {'
            .($hours ? ' hours: httpRequests1hGroups(limit: 48, filter: {datetime_geq: $since, datetime_lt: $until}, orderBy: [datetime_ASC]) { dimensions { datetime } sum { requests threats } }' : '')
            .' days: httpRequests1dGroups(limit: 31, filter: {date_geq: $d0, date_leq: $d1}, orderBy: [date_ASC]) { dimensions { date } sum { requests pageViews threats countryMap { clientCountryName requests threats } } uniq { uniques } }'
            .' } } }';
        $vars = ['zone' => $cf->zone, 'd0' => $from->copy()->utc()->toDateString(), 'd1' => $until->copy()->utc()->toDateString()] + ($hours ? ['since' => self::t($from), 'until' => self::t($until)] : []);
        $r = $cf->graphql($q, $vars);
        $z = $r['data']['viewer']['zones'][0] ?? null;
        if (! $r['ok'] || ! $z) {
            $errors['totals'] = $r['error'] ?: 'Cloudflare sent no figures for this website.';

            return ['totals' => null, 'series' => [], 'countries' => []];
        }
        $totals = ['requests' => 0, 'page_views' => 0, 'visitors' => 0, 'threats' => 0];
        $countries = [];
        foreach ((array) ($z['days'] ?? []) as $d) {
            $totals['requests'] += (int) ($d['sum']['requests'] ?? 0);
            $totals['page_views'] += (int) ($d['sum']['pageViews'] ?? 0);
            $totals['threats'] += (int) ($d['sum']['threats'] ?? 0);
            $totals['visitors'] += (int) ($d['uniq']['uniques'] ?? 0);
            foreach ((array) ($d['sum']['countryMap'] ?? []) as $c) {
                $code = (string) ($c['clientCountryName'] ?? '');
                $countries[$code] = ['code' => $code ?: null, 'requests' => ($countries[$code]['requests'] ?? 0) + (int) ($c['requests'] ?? 0), 'threats' => ($countries[$code]['threats'] ?? 0) + (int) ($c['threats'] ?? 0)];
            }
        }
        usort($countries, fn ($a, $b) => $b['requests'] <=> $a['requests']);

        // the chart, with no gaps: hours today, days otherwise
        $series = [];
        $cur = $from->copy();
        $end = $hours ? $until->copy()->startOfHour() : $until->copy()->startOfDay();
        while ($cur <= $end) {
            $series[$cur->copy()->utc()->format($hours ? 'Y-m-d H' : 'Y-m-d')] = ['t' => $cur->toIso8601String(), 'requests' => 0, 'threats' => 0];
            $hours ? $cur->addHour() : $cur->addDay();
        }
        foreach ((array) ($hours ? ($z['hours'] ?? []) : ($z['days'] ?? [])) as $g) {
            $k = $hours ? Carbon::parse($g['dimensions']['datetime'] ?? '')->utc()->format('Y-m-d H') : (string) ($g['dimensions']['date'] ?? '');
            if (isset($series[$k])) {
                $series[$k]['requests'] += (int) ($g['sum']['requests'] ?? 0);
                $series[$k]['threats'] += (int) ($g['sum']['threats'] ?? 0);
            }
        }

        return ['totals' => $totals, 'series' => array_values($series), 'countries' => array_slice($countries, 0, 20)];
    }

    /** A question for detail data, asked again for the last 24 hours when the plan does not keep the whole range. */
    private static function detail(Cloudflare $cf, string $fields, string $range, Carbon $from, Carbon $until, string $part, array &$errors, array &$notes): ?array
    {
        $q = 'query ($zone: String!, $since: Time!, $until: Time!) { viewer { zones(filter: {zoneTag: $zone}) { '.$fields.' } } }';
        $r = $cf->graphql($q, ['zone' => $cf->zone, 'since' => self::t($from), 'until' => self::t($until)]);
        if (! $r['ok'] && $range !== 'today') {
            $again = $cf->graphql($q, ['zone' => $cf->zone, 'since' => self::t($until->copy()->subDay()), 'until' => self::t($until)]);
            if ($again['ok']) {
                $notes[$part] = 'last_24h';
                $r = $again;
            }
        }
        if (! $r['ok']) {
            $errors[$part] = $r['error'];

            return null;
        }

        return $r['data']['viewer']['zones'][0] ?? [];
    }

    /** Most visited pages and busiest addresses. */
    private static function top(Cloudflare $cf, string $range, Carbon $from, Carbon $until, array &$errors, array &$notes): array
    {
        $z = self::detail($cf, 'paths: httpRequestsAdaptiveGroups(limit: 10, filter: {datetime_geq: $since, datetime_lt: $until}, orderBy: [count_DESC]) { count dimensions { clientRequestPath } }'
            .' ips: httpRequestsAdaptiveGroups(limit: 10, filter: {datetime_geq: $since, datetime_lt: $until}, orderBy: [count_DESC]) { count dimensions { clientIP clientCountryName } }', $range, $from, $until, 'top', $errors, $notes);

        return [
            'paths' => array_map(fn ($p) => ['path' => mb_substr((string) ($p['dimensions']['clientRequestPath'] ?? ''), 0, 160), 'requests' => (int) ($p['count'] ?? 0)], (array) ($z['paths'] ?? [])),
            'ips' => array_map(fn ($p) => ['ip' => (string) ($p['dimensions']['clientIP'] ?? ''), 'country' => ($p['dimensions']['clientCountryName'] ?? '') ?: null, 'requests' => (int) ($p['count'] ?? 0)], (array) ($z['ips'] ?? [])),
        ];
    }

    /** What Cloudflare blocked or challenged, newest first. */
    private static function stopped(Cloudflare $cf, string $range, Carbon $from, Carbon $until, array &$errors, array &$notes): array
    {
        $z = self::detail($cf, 'events: firewallEventsAdaptive(limit: 50, filter: {datetime_geq: $since, datetime_lt: $until}, orderBy: [datetime_DESC]) { action source clientIP clientCountryName clientRequestHTTPMethodName clientRequestPath datetime userAgent }',
            $range, $from, $until, 'stopped', $errors, $notes);

        return ['stopped' => array_map(fn ($e) => [
            'at' => isset($e['datetime']) ? Carbon::parse($e['datetime'])->toIso8601String() : null, 'action' => (string) ($e['action'] ?? ''), 'source' => (string) ($e['source'] ?? ''),
            'ip' => (string) ($e['clientIP'] ?? ''), 'country' => ($e['clientCountryName'] ?? '') ?: null, 'method' => (string) ($e['clientRequestHTTPMethodName'] ?? ''),
            'path' => mb_substr((string) ($e['clientRequestPath'] ?? ''), 0, 160), 'user_agent' => mb_substr((string) ($e['userAgent'] ?? ''), 0, 200),
        ], (array) ($z['events'] ?? []))];
    }
}
