<?php
// includes/ModuleDB.php

class ModuleDB {
    
    /**
     * Allows modules to safely create their own tables.
     * 
     * @param PDO $pdo The database connection (usually SafePDO instance)
     * @param string $tableName The exact name of the table to create
     * @param string $schemaDef The column definitions (everything between the parenthesis of CREATE TABLE)
     */
    public static function createTable(PDO $pdo, string $tableName, string $schemaDef): bool {
        // Enforce safe table names: alphanumeric and underscores only
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            error_log("ModuleDB Error: Invalid table name '{$tableName}'");
            return false;
        }
        
        // Prevent modules from trying to masquerade as core tables
        $protectedCorePrefixes = ['items', 'categories', 'media', 'settings', 'admins', 'tags', 'narratives'];
        if (in_array($tableName, $protectedCorePrefixes)) {
            error_log("ModuleDB Error: Modules cannot declare core table names '{$tableName}'");
            return false;
        }

        try {
            $sql = sprintf('CREATE TABLE IF NOT EXISTS `%s` (%s)', $tableName, $schemaDef);
            $pdo->exec($sql);
            return true;
        } catch (\PDOException | SecurityException $e) {
            error_log("ModuleDB Error creating table {$tableName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Safely drop a module's table.
     */
    public static function dropTable(PDO $pdo, string $tableName): bool {
        // SafePDO handles blocking drops on core tables, but we validate the name format here anyway.
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return false;
        }
        
        try {
            $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
            return true;
        } catch (\PDOException | SecurityException $e) {
            error_log("ModuleDB Error dropping table {$tableName}: " . $e->getMessage());
            return false;
        }
    }
}
