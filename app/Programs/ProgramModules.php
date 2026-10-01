<?php

namespace App\Programs;

use App\Core\Access\PermissionCatalog;

final class ProgramModules
{
    /** What a foundation can create. The list is data-like: a category without a module is a program shell for later. */
    public const CATEGORIES = [
        'aytam' => 'Aytam (orphan care)',
        'relief' => 'Relief',
        'education' => 'Education',
        'healthcare' => 'Healthcare',
        'food_distribution' => 'Food distribution',
        'emergency_assistance' => 'Emergency assistance',
        'qurbani' => 'Qurbani',
        'other' => 'Other',
    ];

    /** @var array<string,ProgramModule> */
    private static array $modules = [];

    public static function register(ProgramModule $module): void
    {
        self::$modules[$module->key()] = $module;
        PermissionCatalog::register(array_map(fn (array $p) => $p + ['scope' => PermissionCatalog::PROGRAM], $module->permissions()));
    }

    public static function get(?string $key): ?ProgramModule
    {
        return $key === null ? null : (self::$modules[$key] ?? null);
    }

    /** @return array<string,ProgramModule> */
    public static function all(): array
    {
        return self::$modules;
    }

    public static function moduleForCategory(string $category): ?ProgramModule
    {
        foreach (self::$modules as $module) {
            if (in_array($category, $module->categories(), true)) {
                return $module;
            }
        }

        return null;
    }

    /** @return array<string,array{name:string,description:string,permissions:list<string>,module:string}> */
    public static function roleTemplates(): array
    {
        $out = [];
        foreach (self::$modules as $module) {
            foreach ($module->roles() as $key => $def) {
                $out[$key] = $def + ['module' => $module->key()];
            }
        }

        return $out;
    }
}
