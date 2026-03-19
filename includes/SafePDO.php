<?php
// includes/SafePDO.php

class SecurityException extends Exception {}

class SafePDO extends PDO {
    
    // Core tables that should never be DROPped, ALTERed, or TRUNCATEd by a module or any dynamic query
    private array $protectedTables = [
        'items', 'categories', 'media', 'settings', 'admins', 
        'tags', 'item_tag', 'narratives', 'item_narrative'
    ];

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {
        parent::__construct($dsn, $username, $password, $options);
    }
    
    /**
     * Inspect a query string for DDL statements (DROP, ALTER, TRUNCATE) against protected tables.
     */
    private function checkQuerySafety(string $statement): void {
        $upperQuery = strtoupper(trim($statement));
        
        // Quick short-circuit if it's not a dangerous schema change command
        if (!preg_match('/^(DROP|ALTER|TRUNCATE)\s+/i', $upperQuery)) {
            return;
        }

        foreach ($this->protectedTables as $table) {
            // Check if the query contains the protected table name, bounded by word boundaries or backticks
            if (preg_match("/\b`?{$table}`?\b/i", $upperQuery)) {
                error_log("SECURITY VIOLATION: Attempted DDL operation on protected table '{$table}'. Query: {$statement}");
                throw new SecurityException("Cannot execute DDL operations (DROP, ALTER, TRUNCATE) on protected core table: {$table}");
            }
        }
    }

    public function exec(string $statement): int|false {
        $this->checkQuerySafety($statement);
        return parent::exec($statement);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $this->checkQuerySafety($query);
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->checkQuerySafety($query);
        return parent::prepare($query, $options);
    }
}
