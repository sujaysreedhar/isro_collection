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
     *
     * Auto-dispatches to optional named sub-methods so large modules can split
     * concerns cleanly. Override any of: registerRoutes(), registerAdminMenu(),
     * registerSearch(), registerHooks(). Simple modules may instead override
     * boot() directly and ignore the sub-methods entirely.
     */
    public function boot() {
        if (method_exists($this, 'registerRoutes'))    $this->registerRoutes();
        if (method_exists($this, 'registerAdminMenu')) $this->registerAdminMenu();
        if (method_exists($this, 'registerSearch'))    $this->registerSearch();
        if (method_exists($this, 'registerHooks'))     $this->registerHooks();
    }

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
