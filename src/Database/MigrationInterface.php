<?php

declare(strict_types=1);

namespace AssociationManager\Database;

interface MigrationInterface
{
    public function id(): string;

    public function up(): void;
}