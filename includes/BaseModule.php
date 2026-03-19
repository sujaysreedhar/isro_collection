<?php
// includes/BaseModule.php

abstract class BaseModule {
    protected $pdo;
    protected $slug;
    protected $metadata;

    public function __construct(PDO $pdo, string $slug, array $metadata) {
        $this->pdo = $pdo;
        $this->slug = $slug;
        $this->metadata = $metadata;
    }

    /**
     * Called on every request if the module is active.
     * Register hooks, filters, etc. here.
     */
    abstract public function boot();

    /**
     * Called when the module is first enabled.
     * Handle table creation or initial setup.
     */
    public function activate() {
        // Optional override
    }

    /**
     * Called when the module is disabled.
     * Handle cleanup if necessary.
     */
    public function deactivate() {
        // Optional override
    }

    public function getSlug(): string {
        return $this->slug;
    }

    public function getMetadata(): array {
        return $this->metadata;
    }
}
