<?php

namespace App\Programs;

/**
 * A program type with its own data model, screens, permissions and roles. The foundation core never mentions a
 * module by name: it asks the registry. A new module (Relief, Education...) is added by writing one of these and
 * registering it - no change to foundations, users, roles or programs.
 */
abstract class ProgramModule
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** @return list<string> program categories this module serves */
    abstract public function categories(): array;

    /** @return array<string,array{label:string,group:string}> program-scope permissions */
    abstract public function permissions(): array;

    /** @return array<string,array{name:string,description:string,permissions:list<string>}> program role templates, by key */
    abstract public function roles(): array;

    /** @return array<string,mixed> */
    public function defaultConfig(): array
    {
        return [];
    }

    /** Validation rules for the keys of a program's `config`. @return array<string,mixed> */
    public function configRules(): array
    {
        return [];
    }

    /** The program permission that lets a module's own supervisor configure the program (partners, requirements). */
    public function configurePermission(): ?string
    {
        return null;
    }
}
