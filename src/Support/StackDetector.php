<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Support;

use JsonException;

/**
 * Works out which frontend stack an application is built on.
 *
 * Detection reads the application's own manifests rather than guessing from
 * directory layout, because a project can keep stray view folders long after it
 * has moved to Inertia.
 *
 * Nothing here influences the package at runtime; it exists only so the
 * scaffolding command can publish stubs that match the host application.
 */
final class StackDetector
{
    public const BLADE = 'blade';

    public const LIVEWIRE = 'livewire';

    public const REACT = 'react';

    public const VUE = 'vue';

    public const SVELTE = 'svelte';

    /** @var list<string> */
    public const ALL = [self::BLADE, self::LIVEWIRE, self::REACT, self::VUE, self::SVELTE];

    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * The stack to scaffold for, falling back to Blade.
     *
     * Inertia is checked before Livewire: an application can have both
     * installed, but if it renders through Inertia the components have to be
     * JavaScript.
     */
    public function detect(): string
    {
        if ($this->hasComposerPackage('inertiajs/inertia-laravel')) {
            $client = $this->detectInertiaClient();

            if ($client !== null) {
                return $client;
            }
        }

        // A JavaScript client without the server adapter still means Inertia.
        $client = $this->detectInertiaClient();

        if ($client !== null) {
            return $client;
        }

        if ($this->hasComposerPackage('livewire/livewire')) {
            return self::LIVEWIRE;
        }

        return self::BLADE;
    }

    /**
     * Everything detection found, for reporting back to the developer.
     *
     * @return array{stack: string, inertia: bool, livewire: bool, tailwind: bool, client: ?string}
     */
    public function report(): array
    {
        return [
            'stack' => $this->detect(),
            'inertia' => $this->hasComposerPackage('inertiajs/inertia-laravel'),
            'livewire' => $this->hasComposerPackage('livewire/livewire'),
            'tailwind' => $this->hasTailwind(),
            'client' => $this->detectInertiaClient(),
        ];
    }

    public function hasTailwind(): bool
    {
        if ($this->hasNodePackage('tailwindcss')) {
            return true;
        }

        // Tailwind 4 is often pulled in only through the Vite plugin.
        return $this->hasNodePackage('@tailwindcss/vite');
    }

    public static function isValid(string $stack): bool
    {
        return in_array($stack, self::ALL, true);
    }

    private function detectInertiaClient(): ?string
    {
        foreach ([
            '@inertiajs/react' => self::REACT,
            '@inertiajs/vue3' => self::VUE,
            '@inertiajs/svelte' => self::SVELTE,
        ] as $package => $stack) {
            if ($this->hasNodePackage($package)) {
                return $stack;
            }
        }

        return null;
    }

    private function hasComposerPackage(string $name): bool
    {
        $manifest = $this->json('composer.json');

        return isset($manifest['require'][$name]) || isset($manifest['require-dev'][$name]);
    }

    private function hasNodePackage(string $name): bool
    {
        $manifest = $this->json('package.json');

        return isset($manifest['dependencies'][$name]) || isset($manifest['devDependencies'][$name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $file): array
    {
        $path = rtrim($this->basePath, '/\\').DIRECTORY_SEPARATOR.$file;

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
