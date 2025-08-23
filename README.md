# WP Tables Schema

A fluent, powerful, and developer-friendly library for creating and managing custom database tables in WordPress. Define your table schemas with an expressive API, handle versioning and migrations with ease, and build robust, modern data layers for your plugins.

---

## Key Features

-   **Fluent Schema Builder**: Effortlessly define table columns, indexes, and relationships using a clean, chainable API.
-   **Automated Migrations**: A simple, version-based migration system lets you evolve your database schema safely and predictably over time.
-   **WordPress Integration**: Built to work seamlessly with the `$wpdb` object, prefixes, and the WordPress environment, including full multisite support.
-   **Code-First Approach**: Define your entire database schema in version-controllable PHP, making it easy to track changes and collaborate.
-   **Rich Type & Index Support**: Supports a wide range of MySQL/MariaDB column types, all index types (`UNIQUE`, `FULLTEXT`, `SPATIAL`), and foreign key constraints.
-   **Powerful Helpers**: Includes an abstract `Meta_Table` class to instantly create WordPress-style metadata tables, plus schema macros for common patterns like timestamps and soft deletes.

## Requirements

-   PHP 8.0+
-   WordPress 5.0+
-   MySQL 5.6+ or MariaDB 10.1+

## Installation

The recommended way to install this library is through [Composer](https://getcomposer.org/):

```bash
composer require wptechnix/wp-tables-schema
```

## Quick Start Guide

Let's create a custom database table for "Events" in 2 simple steps.

### Step 1: Define Your Table Class

Create a new PHP class that extends `Table`. This class is the single source of truth for your table's structure and version.

**`src/Database/Tables/Events_Table.php`**
```php
<?php

namespace Your_Plugin\Database\Tables;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

class Events_Table extends Table
{
    /** The schema version. Increment this to run a new migration. */
    protected int $schema_version = 10001;

    /** The table name, without prefixes. */
    protected string $table_name = 'events';
    
    /** A prefix for your plugin's tables to avoid conflicts. */
    protected string $plugin_prefix = 'my_plugin_';
    
    // --- The rest of these properties are optional helpers ---
    protected string $table_singular_name = 'event';
    protected string $table_alias = 'e';
    protected string $foreign_key_name = 'event_id';

    /**
     * The migration method to create the initial table structure.
     * The method name `migrate_to_10001` matches the `$schema_version`.
     */
    protected function migrate_to_10001(): bool
    {
        return $this->create_table(
            function (Create_Table_Schema $schema) {
                // Creates an auto-incrementing `id` column as the primary key.
                $schema->id();

                // Creates a `title` column (VARCHAR) and adds an index to it.
                $schema->string('title')->index();
                
                // Creates a `location` column that can be NULL.
                $schema->string('location')->nullable();

                // Creates a `starts_at` DATETIME column.
                $schema->datetime('starts_at');
                
                // Creates `created_at` and `updated_at` columns automatically.
                $schema->timestamps();

                return $schema;
            }
        );
    }
}
```

### Step 2: Run the Installation

In your main plugin file, hook a function to `register_activation_hook` to run the installer.

**`my-plugin.php`**

```php
<?php

use Your_Plugin\Database\Tables\Events_Table;

/**
 * The main installation function for the plugin.
 */
function my_plugin_install() {
    global $wpdb;

    // Create an instance of the table class and run the installation.
    $events_table = new Events_Table($wpdb);
    $events_table->install();
}

// Hook our installation function to run only when the plugin is activated.
register_activation_hook(__FILE__, 'my_plugin_install');
```

That's it! When you activate your plugin, a `wp_my_plugin_events` table will be created with the exact structure you defined.

---

## Core Concepts

-   **Schema Builder**: The fluent interface inside `create_table()` is where you define your table's columns (`string`, `integer`, etc.), modifiers (`nullable`, `default`), and indexes (`index`, `unique`).
    -   [Learn more in the Schema Builder Guide](./docs/02-The-Schema-Builder.md)

-   **Migrations**: To change a table, you simply increment `$schema_version` (e.g., to `10002`) and add a new `migrate_to_10002()` method. The library automatically detects and runs the new migration. You can add columns, drop indexes, and more.
    -   [Learn how to evolve tables with the Migrations Guide](./docs/03-Evolving-Tables-with-Migrations.md)

-   **Relationships**: You can easily create foreign key constraints to link tables, ensuring data integrity. The library provides clear, fluent methods for defining these critical relationships.
    -   [Learn more in the Relationships Guide](./docs/04-Managing-Table-Relationships.md)

-   **Helpers & Shortcuts**: The library is packed with helpers like the `Meta_Table` class for creating metadata tables in just a few lines of code, and schema macros like `timestamps()` and `soft_deletes()` to speed up development.
    -   [Discover all the helpers in the Shortcuts Guide](./docs/05-Shortcuts-and-Helpers.md)

## Full Documentation

For a deep dive into every feature, please refer to the complete documentation:

1.  [**Getting Started**](./docs/01-Getting-Started.md)
2.  [**The Schema Builder**](./docs/02-The-Schema-Builder.md)
3.  [**Evolving Tables with Migrations**](./docs/03-Evolving-Tables-with-Migrations.md)
4.  [**Managing Table Relationships**](./docs/04-Managing-Table-Relationships.md)
5.  [**Shortcuts and Helpers**](./docs/05-Shortcuts-and-Helpers.md)
6.  [**Advanced Topics**](./docs/06-Advanced-Topics.md)