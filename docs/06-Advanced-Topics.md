# 6. Advanced Topics

Congratulations on making it this far! You are now equipped with all the core skills for creating, managing, and relating database tables. This final guide covers specialized features for handling unique requirements like multisite networks, conditional logic, and robust error logging.

---

### 1. Fine-Tuning Table Creation Options

While the library provides sensible defaults, you can fine-tune the table-level options directly within the schema builder.

| Method | Description | Example SQL |
| :--- | :--- | :--- |
| `->engine($name)` | Sets the database storage engine. `InnoDB` is highly recommended for foreign key support. | `ENGINE=InnoDB` |
| `->charset($name)` | Sets the default character set for the table. | `DEFAULT CHARACTER SET=utf8mb4` |
| `->collation($name)`| Sets the default collation for character sorting and comparison. | `COLLATE=utf8mb4_unicode_ci` |
| `->comment($text)` | Adds a descriptive comment to the table itself in the database schema. | `COMMENT='Stores all user-generated tasks.'` |
| `->if_not_exists()`| Adds the `IF NOT EXISTS` clause to the `CREATE TABLE` statement. This prevents an error if the table already exists, though the library's versioning system already handles this safely. | `CREATE TABLE IF NOT EXISTS...` |

**Example Usage:**
```php
protected function migrate_to_10001(): bool
{
    return $this->create_table(function (Create_Table_Schema $schema) {
        // Apply table-level options at the top of the closure.
        $schema->engine('InnoDB');
        $schema->comment('Stores all user-generated tasks and their status.');

        // Define columns as usual.
        $schema->id();
        $schema->string('title');
        
        return $schema;
    });
}
```

---

### 2. Multisite Installations

By default, every table you create is **site-specific**. In a multisite network, this means each site will get its own version of the table (e.g., `wp_2_tasks`, `wp_3_tasks`).

However, sometimes you need a single table that is **shared across all sites** in the network. You can achieve this by setting one property in your `Table` class.

-   `protected bool $multisite_shared = false;` (Default): Uses the site-specific prefix (`$wpdb->prefix`).
-   `protected bool $multisite_shared = true;`: Uses the global network prefix (`$wpdb->base_prefix`).

**Example**: Let's create a `global_logs` table that is shared across the entire network.
```php
<?php
// In Global_Logs_Table.php

class Global_Logs_Table extends Table
{
    // ... other properties
    protected string $table_name = 'global_logs';
    protected string $plugin_prefix = 'my_plugin_';
    
    /**
     * By setting this to true, the table will be created using the base prefix
     * (e.g., `wp_my_plugin_global_logs`) and will be accessible from all sites.
     * @var bool
     */
    protected bool $multisite_shared = true;
    
    // ... migration methods
}
```

---

### 3. Conditional Migrations

Your plugin may be installed on a wide variety of server environments. Sometimes, you might want to run a migration that uses a feature only available in a newer version of MySQL or MariaDB. The library includes helper methods to check the database version safely.

-   `is_mysql_at_least('version_string')`
-   `is_mariadb_at_least('version_string')`

**Example**: Imagine a new, faster index type becomes available in MariaDB 10.6. You want to upgrade an index for users on that version, but not break sites on older versions.
```php
protected function migrate_to_10005(): bool
{
    // Check if the site is running a compatible MariaDB version.
    if ($this->is_mariadb_at_least('10.6')) {
    
        // Only run this advanced logic for compatible sites.
        $this->drop_index('idx_tasks_title');
        
        // This is a hypothetical example of a version-specific feature.
        $this->wpdb->query(
            "ALTER TABLE {$this->get_table_name()} ADD INDEX idx_tasks_title (title) COMMENT 'Using new algorithm'"
        );
    }
    
    // IMPORTANT: Always return true so the version number is updated for everyone,
    // even if the conditional logic didn't run.
    return true;
}
```

---

### 4. Logging for Effective Debugging

Database migrations can sometimes fail, especially on live servers with unexpected configurations. Debugging these issues can be difficult. To solve this, the library supports the **PSR-3 Logger Interface**, a popular standard for logging in PHP.

By providing a logger, you can automatically capture any errors that happen during the `install()` process to a log file, complete with detailed messages and stack traces.

#### Step 1: Install a Logger
First, add a PSR-3 compliant logger to your project. **Monolog** is the most popular and recommended choice.
```bash
composer require monolog/monolog
```

#### Step 2: Create and Inject the Logger
In your main installation function, create an instance of your logger and pass it to your `Table` object using the `setLogger()` method.

**`my-plugin.php`**
```php
<?php
use Your_Plugin\Database\Tables\Tasks_Table;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

function my_plugin_install() {
    global $wpdb;

    // --- Logger Setup ---
    // 1. Create a new logger instance. 'db-migrations' is the channel name.
    $log = new Logger('db-migrations');
    
    // 2. Create a handler to write logs to a file in your plugin's directory.
    //    We'll only log messages that are WARNING level or higher.
    $log_file = __DIR__ . '/migrations.log';
    $log->pushHandler(new StreamHandler($log_file, Level::Warning));
    // ------------------
    
    // Instantiate your table.
    $tasks_table = new Tasks_Table($wpdb);

    // 3. Inject the logger into the table object.
    $tasks_table->setLogger($log);
    
    // Now, run the installation.
    $tasks_table->install(); 
}

register_activation_hook(__FILE__, 'my_plugin_install');
```
Now, if any `Tasks_Table` migration fails due to a SQL error or any other exception, a `migrations.log` file will appear in your plugin's root directory. It will contain a detailed error message, making it incredibly easy to diagnose and fix the problem.

---

You have now completed the full documentation for WP Tables Schema. You have the knowledge to build simple tables, evolve them with complex migrations, connect them with relationships, and manage advanced, real-world scenarios with confidence. Happy coding