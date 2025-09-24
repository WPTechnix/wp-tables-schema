# 1. Getting Started

Welcome to WP Tables Schema! This guide will walk you through the fundamental steps of setting up the library and creating your first custom database table.

---

### Installation

First, ensure you have installed the library in your project using Composer:

```bash
composer require wptechnix/wp-tables-schema
```

Make sure your plugin includes the Composer autoloader:

```php
<?php
// my-plugin.php

require_once __DIR__ . '/vendor/autoload.php';
```

---

### Core Concepts

Before we build a table, let's understand the key ideas behind the library.

#### The `Table` Class

The foundation of the library is the abstract `Table` class. To create a custom database table, you will create a new PHP class that **extends** `Table`. This new class is the single source of truth for your table's structure and its migration history.

#### Schema Versioning

Every table class has a `$schema_version` property. This integer is crucial for managing database updates.

-   When you first create a table, you'll set it to a starting version (e.g., `10001`).
-   When you need to change the table later (e.g., add a new column), you will add a new migration method and **increment** this version number (e.g., to `10002`).

The library uses this version to know which updates need to be run.

#### The `install()` Method

This is the main method you'll call. When `$my_table->install()` is executed, the library automatically:
1.  Checks the current version of your table stored in the WordPress options table.
2.  Compares it to the `$schema_version` defined in your PHP class.
3.  Runs any necessary migration methods in order until the database schema is up to date.

---

### Step-by-Step: Creating Your First Table

Let's create a simple table to store a list of "Tasks".

#### Step 1: Define the Table Class

First, create a new PHP file for your table definition. A good practice is to keep these in a dedicated directory, like `src/Database/Tables/`.

**`src/Database/Tables/Tasks_Table.php`**
```php
<?php

namespace Your_Plugin\Database\Tables;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

class Tasks_Table extends Table
{
    protected int $schema_version = 10001;
    protected string $table_name = 'tasks';
    protected string $table_singular_name = 'task';
    protected string $foreign_key_name = 'task_id';
    protected string $plugin_prefix = 'my_plugin_';

    /**
     * This method defines the initial table structure.
     * The method name `migrate_to_10001` must match the `$schema_version`.
     */
    protected function migrate_to_10001(): bool
    {
        return $this->create_table(
            function (Create_Table_Schema $schema) {
                $schema->id(); // A standard primary key column named 'id'.
                $schema->string('title')->index(); // A title column with an index.
                $schema->string('priority', 20)->default('normal');
                $schema->datetime('completed_at')->nullable();
                $schema->timestamps(); // `created_at` and `updated_at` columns.
                return $schema;
            }
        );
    }
}
```

#### Step 2: Trigger the Installation

Now, you need to tell WordPress when to run this installation. The best practice for creating tables is to use `register_activation_hook`, which runs only once when the user activates your plugin.

**`my-plugin.php`**
```php
<?php

use Your_Plugin\Database\Tables\Tasks_Table;

/**
 * The main installation function for the plugin.
 */
function my_plugin_install() {
    global $wpdb;

    // Create an instance of our table class.
    $tasks_table = new Tasks_Table($wpdb);

    // Call the install() method to create the table.
    $tasks_table->install();
}

// Hook our installation function to the plugin's activation.
register_activation_hook(__FILE__, 'my_plugin_install');
```

And you're done! When a user activates your plugin, the library will automatically create the `wp_my_plugin_tasks` table with the exact schema you defined.

### What's Next?

You've successfully created a table. Now it's time to learn about all the powerful tools available in the schema builder to define your columns and indexes with precision.

-   [**Next: The Schema Builder &rarr;**](./02-The-Schema-Builder.md)
