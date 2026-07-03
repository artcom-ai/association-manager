<?php

declare(strict_types=1);

namespace AssociationManager\Core;

defined('ABSPATH') || exit;

final class ModuleManager
{
    /**
     * @var ModuleInterface[]
     */
    private array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    public function boot(Container $container): void
    {
        foreach ($this->modules as $module) {
            $module->register($container);
        }

        foreach ($this->modules as $module) {
            $module->boot($container);
        }
    }

    public function all(): array
    {
        return $this->modules;
    }
}