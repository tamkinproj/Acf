<?php

namespace App\Sync;

use App\Models\User;

/** Registry of replicable entities. Core registers its own; modules register theirs from a service provider. */
class SyncRegistry
{
    /** @var array<string,EntityDefinition> */
    private array $entities = [];

    public function register(EntityDefinition $definition): void
    {
        $this->entities[$definition->name] = $definition;
    }

    public function get(string $name): ?EntityDefinition
    {
        return $this->entities[$name] ?? null;
    }

    /** @return array<string,EntityDefinition> */
    public function all(): array
    {
        return $this->entities;
    }

    public function entityNameFor(string $modelClass): ?string
    {
        foreach ($this->entities as $name => $def) {
            if ($def->model === $modelClass) {
                return $name;
            }
        }

        return null;
    }

    /** @return list<string> entity names this user may pull */
    public function pullableFor(User $user): array
    {
        return array_keys(array_filter($this->entities, fn (EntityDefinition $d) => $d->canPull($user)));
    }
}
