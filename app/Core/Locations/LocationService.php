<?php

namespace App\Core\Locations;

use App\Models\Foundation;
use App\Models\Location;
use App\Sync\RejectChange;
use Illuminate\Validation\Rule;

/**
 * Location hierarchy rules. The tree (path/depth) is derived on the server,
 * never trusted from a device, so concurrent edits can't corrupt it.
 */
class LocationService
{
    public function rules(string $op, ?Location $existing, array $fields): array
    {
        $req = $op === 'create' ? ['required'] : ['sometimes', 'required'];

        return [
            'parent_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'level' => [...$req, Rule::in(array_keys(Location::LEVELS))],
            'name' => [...$req, 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Derive path/depth and enforce the hierarchy. @return array<string,mixed> fields to save */
    public function prepare(string $op, ?Location $existing, array $fields): array
    {
        $id = $existing?->getKey() ?? $fields['id'];
        $parentId = array_key_exists('parent_id', $fields) ? $fields['parent_id'] : $existing?->parent_id;
        $level = $fields['level'] ?? $existing?->level;

        $parent = $parentId ? Location::query()->find($parentId) : null;
        if ($parent) {
            if ($parent->getKey() === $id || str_contains($parent->path, "/{$id}/")) {
                throw new RejectChange('hierarchy', 'A location cannot be moved inside itself.');
            }
            if (Location::LEVELS[$level] <= Location::LEVELS[$parent->level]) {
                throw new RejectChange('hierarchy', "A {$level} cannot sit under a {$parent->level}.");
            }
        }
        if ($existing && isset($fields['level'])) {
            $deepestShallow = $existing->children()->get()->first(fn (Location $c) => Location::LEVELS[$c->level] <= Location::LEVELS[$level]);
            if ($deepestShallow) {
                throw new RejectChange('hierarchy', "Existing sub-locations are not deeper than a {$level}.");
            }
        }

        $fields['path'] = ($parent?->path ?? '/').$id.'/';
        $fields['depth'] = $parent ? $parent->depth + 1 : 0;
        unset($fields['id']);

        return $fields;
    }

    public function guardDelete(Location $location): void
    {
        if ($location->children()->exists()) {
            throw new RejectChange('has_children', 'Remove or move the sub-locations first.');
        }
        if (Foundation::query()->where('default_location_id', $location->getKey())->exists()) {
            throw new RejectChange('in_use', 'This is the foundation\'s default location.');
        }
    }

    /** After a move, rewrite every descendant's path/depth (each save replicates). */
    public function refreshDescendants(Location $moved, string $oldPath): void
    {
        Location::query()->where('path', 'like', $oldPath.'%')->where('id', '!=', $moved->getKey())
            ->orderBy('depth')->get()
            ->each(function (Location $child) use ($moved, $oldPath) {
                $child->path = $moved->path.substr($child->path, strlen($oldPath));
                $child->depth = substr_count(trim($child->path, '/'), '/');
                $child->save();
            });
    }
}
