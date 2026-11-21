<?php

namespace App\Support;

use App\Models\Child;
use App\Models\ChildApplication;
use App\Models\Project;
use App\Models\User;

/**
 * The cards that can be shared into a chat. A card is a LINK (kind + id), never a copy of the data: every time someone
 * looks at the chat, the server describes the card for THAT viewer. Someone without the permission for a kind sees only
 * the kind and (for orphans and applications) the reference number, and no name, amount or detail.
 */
final class ChatCards
{
    /** kind => permission needed to open it */
    public const KINDS = [
        'child' => 'children.view',
        'application' => 'applications.review',
        'project' => 'projects.view',
        'payment' => 'payments.view',
        'user' => 'users.manage',
        'report' => 'reports.view',
        'site' => 'site.edit',
    ];

    public static function valid(string $kind): bool
    {
        return array_key_exists($kind, self::KINDS);
    }

    public static function canOpen(User $viewer, string $kind): bool
    {
        return self::valid($kind) && PermissionCatalog::allows($viewer, self::KINDS[$kind]);
    }

    /** Kinds this person may share (= may open). */
    public static function shareable(User $viewer): array
    {
        return array_values(array_filter(array_keys(self::KINDS), fn ($k) => self::canOpen($viewer, $k)));
    }

    public static function validMonth(?string $m): bool
    {
        return $m !== null && (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m);
    }

    /** Does the thing exist in the signed-in person's foundation? (The models are scoped to it.) */
    public static function exists(string $kind, ?int $id, ?string $extra): bool
    {
        return match ($kind) {
            'child' => $id && Child::whereKey($id)->exists(),
            'application' => $id && ChildApplication::whereKey($id)->exists(),
            'project' => $id && Project::whereKey($id)->exists(),
            'payment' => $id && self::validMonth($extra) && Child::whereKey($id)->exists(),
            'user' => $id && User::whereKey($id)->where('foundation_id', auth()->user()?->foundation_id)->exists(),
            'report' => self::validMonth($extra),
            'site' => true,
            default => false,
        };
    }

    /**
     * How a card looks to this viewer: [kind, access, label, sub, href].
     * Without access: no names, no amounts, no link (the destination page would refuse them anyway).
     */
    public static function describe(User $viewer, string $kind, ?int $id, ?string $extra): array
    {
        $access = self::canOpen($viewer, $kind);
        $card = ['kind' => $kind, 'access' => $access, 'label' => ucfirst($kind), 'sub' => null, 'href' => null];

        switch ($kind) {
            case 'child':
                $c = $id ? Child::withoutGlobalScopes()->where('foundation_id', $viewer->foundation_id)->find($id) : null;
                if (! $c) {
                    return $card + ['gone' => true];
                }
                $card['label'] = $access ? ($c->name_en ?: $c->name_ar ?: $c->ref) : 'Orphan '.$c->ref;
                $card['sub'] = $c->ref.' · '.$c->status;
                $card['href'] = $access ? 'person.html?id='.$c->id : null;
                break;
            case 'application':
                $a = $id ? ChildApplication::withoutGlobalScopes()->where('foundation_id', $viewer->foundation_id)->find($id) : null;
                if (! $a) {
                    return $card + ['gone' => true];
                }
                $d = (array) $a->data;
                $card['label'] = $access ? (($d['name_en'] ?? null) ?: ($d['name_ar'] ?? null) ?: $a->reference()) : 'Application '.$a->reference();
                $card['sub'] = $a->reference().' · '.$a->status;
                $card['href'] = $access ? 'application.html?id='.$a->id : null;
                break;
            case 'project':
                $p = $id ? Project::withoutGlobalScopes()->where('foundation_id', $viewer->foundation_id)->find($id) : null;
                if (! $p) {
                    return $card + ['gone' => true];
                }
                $card['label'] = $access ? ($p->name_en ?: $p->name_ar) : 'Project';
                $card['sub'] = $access ? $p->status : null;
                $card['href'] = $access ? 'projects.html?focus='.$p->id : null;
                break;
            case 'payment':
                $c = $id ? Child::withoutGlobalScopes()->where('foundation_id', $viewer->foundation_id)->find($id) : null;
                if (! $c) {
                    return $card + ['gone' => true];
                }
                $card['label'] = $access ? 'Payment · '.($c->name_en ?: $c->name_ar ?: $c->ref) : 'Payment';
                $card['sub'] = $extra;
                $card['href'] = $access ? 'payments.html?month='.$extra.'&focus='.$c->id : null;
                break;
            case 'user':
                $u = $id ? User::where('foundation_id', $viewer->foundation_id)->find($id) : null;
                if (! $u) {
                    return $card + ['gone' => true];
                }
                $card['label'] = $access ? $u->name : 'Account';
                $card['sub'] = $access ? config("roles.roles.{$u->role}.label") : null;
                $card['href'] = $access ? 'users.html?focus='.$u->id : null;
                break;
            case 'report':
                $card['label'] = 'Report';
                $card['sub'] = $extra;
                $card['href'] = $access ? 'reports.html?month='.$extra : null;
                break;
            case 'site':
                $card['label'] = 'Landing page';
                $card['href'] = $access ? 'website.html' : null;
                break;
        }

        return $card;
    }
}
