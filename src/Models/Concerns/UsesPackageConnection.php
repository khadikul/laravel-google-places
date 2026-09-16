<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Models\Concerns;

/**
 * Lets every package model honour the configured table names and database
 * connection, so the host application can relocate them without subclassing.
 *
 * The table key is exposed as a method rather than a property: a trait property
 * and a class property must be declared identically, including their default,
 * which would defeat the point of letting each model name its own key.
 */
trait UsesPackageConnection
{
    /**
     * Key inside config("google-places.database.tables").
     */
    abstract protected function packageTableKey(): string;

    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $configured = config('google-places.database.tables.'.$this->packageTableKey());

        return is_string($configured) && $configured !== ''
            ? $configured
            : parent::getTable();
    }

    public function getConnectionName(): ?string
    {
        $connection = config('google-places.database.connection');

        return is_string($connection) && $connection !== ''
            ? $connection
            : $this->connection;
    }
}
