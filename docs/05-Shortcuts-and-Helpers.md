# 5. Shortcuts and Helpers

You now have the core skills to build and manage any table structure. This guide introduces the library's powerful shortcuts and helpers, designed to handle common database patterns for you, saving you time and reducing boilerplate code.

---

## Part 1: The `Meta_Table` Helper

In WordPress, it's a very common pattern to have a main table (like `wp_posts`) and a corresponding metadata table (`wp_postmeta`) to store flexible key-value data. The abstract `Meta_Table` class automates this entire process for you.

When you use `Meta_Table`, it handles everything:
-   It defines the standard `meta_id`, `meta_key`, and `meta_value` columns.
-   It automatically creates the foreign key column (e.g., `task_id`) based on the parent table you provide.
-   It writes the entire initial migration and sets up the foreign key relationship for you.

Your only job is to tell it which parent table it belongs to.

### Step-by-Step Guide

Let's create a `tasks_meta` table for our `Tasks_Table`.

#### 1. Create the Meta Table Class
Create a new file, `src/Database/Tables/Tasks_Meta_Table.php`. Instead of extending `Table`, you will **extend `Meta_Table`**.

This class is incredibly minimal. You only need to define two properties:
1.  `$table_singular_name`: The singular name of the **parent object**. For a `tasks` table, this would be `'task'`. The class uses this to name the table (`taskmeta`) and the foreign key (`task_id`).
2.  `$plugin_prefix`: Your plugin's prefix.

You do **not** need to create a constructor or a `migrate_to_10001()` method. The parent `Meta_Table` class does it all.

**`src/Database/Tables/Tasks_Meta_Table.php`**
```php
<?php

namespace Your_Plugin\Database\Tables;

use WPTechnix\WP_Tables_Schema\Meta_Table;

class Tasks_Meta_Table extends Meta_Table
{
    /**
     * The singular name of the PARENT.
     * This is the only required piece of configuration.
     * @var string
     */
    protected string $table_singular_name = 'task';

    /**
     * The plugin's prefix.
     * @var string
     */
    protected string $plugin_prefix = 'my_plugin_';
}
```

#### 2. Update the Installer
Next, go to your main installer function. The `Meta_Table` constructor requires the **parent table object** as its first argument.

**`my-plugin.php`**
```php
<?php

use Your_Plugin\Database\Tables\Tasks_Table;
use Your_Plugin\Database\Tables\Tasks_Meta_Table;

function my_plugin_install() {
    global $wpdb;

    // 1. Instantiate the parent table first.
    $tasks_table = new Tasks_Table($wpdb);

    // 2. Instantiate the meta table, passing the parent table object into it.
    $tasks_meta_table = new Tasks_Meta_Table($tasks_table, $wpdb);

    // 3. Run install() on both. It's best practice to install the parent first.
    $tasks_table->install();
    $tasks_meta_table->install();
}

register_activation_hook(__FILE__, 'my_plugin_install');
```
That's it! The library will automatically create the `wp_my_plugin_taskmeta` table with a perfect schema and a foreign key relationship to the `tasks` table.

---

## Part 2: Schema Builder Macros

The schema builder includes several "macro" methods that are convenient shortcuts for common column patterns.

### `->id()`
You've already seen this one! It's a macro that creates a `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` column, which is the perfect primary key for most tables.
`$schema->id();`

### `->timestamps()`
This macro adds two nullable `DATETIME` columns for tracking when a record is created and updated.
-   `created_at`: Defaults to the current timestamp when a row is inserted.
-   `updated_at`: Defaults to the current timestamp and automatically updates whenever the row is changed.

`$schema->timestamps();`

### `->soft_deletes()`
This macro adds a single nullable `deleted_at` `DATETIME` column. This is used for "soft deleting"—marking a row as deleted by setting a timestamp, instead of actually removing it from the database.

`$schema->soft_deletes();`

### `->morphs()`
This powerful macro is for creating **polymorphic relationships**. This is when a model (like an image or a comment) can belong to more than one other type of model.

The `->morphs($name)` method creates two columns and a composite index on them:
-   `{$name}_id` (BIGINT UNSIGNED): Stores the ID of the parent model.
-   `{$name}_type` (VARCHAR): Stores a string identifying the parent's type (e.g., 'task' or 'project').

**Example**: Let's create a `notes` table where notes can be attached to both `Tasks` and `Projects`.
```php
protected function migrate_to_10001(): bool
{
    return $this->create_table(function (Create_Table_Schema $schema) {
        $schema->id();
        $schema->text('content');
        
        // This creates 'notable_id' and 'notable_type' columns, plus an index.
        $schema->morphs('notable');
        
        $schema->timestamps();
        
        return $schema;
    });
}
```
A note with `notable_id` = 15 and `notable_type` = 'task' belongs to Task #15. If `notable_type` were 'project', it would belong to Project #15.

---

## Part 3: Your Table Class as a Helper

Your `Table` class isn't just for migrations; it's also a dynamic source of information about your table's structure. Using its getter methods in your code is far better than hardcoding table and column names.

-   `get_table_name()`: Returns the full, prefixed table name.
-   `get_primary_key()`: Returns the name of the primary key column.
-   `get_foreign_key_name()`: Returns the conventional name for this table's foreign key when used in other tables.
-   `get_table_alias()`: Returns the short alias for use in SQL JOINs.

**Example**: Writing a clean, future-proof query to get a task by its ID.
```php
function get_task_by_id(int $task_id) {
    global $wpdb;
    
    // Instantiate the table to get its properties.
    $tasks_table = new Tasks_Table($wpdb);
    
    $table_name = $tasks_table->get_table_name();
    $primary_key = $tasks_table->get_primary_key();
    
    // This query is now clean and resilient to future changes.
    // If you ever rename the table or primary key, this code still works.
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM `{$table_name}` WHERE `{$primary_key}` = %d",
            $task_id
        )
    );
}
```

### What's Next?

You are now familiar with the library's core features and powerful helpers. The final guide will cover specialized, advanced topics for handling unique requirements and debugging your migrations.

-   [**Next: Advanced Topics &rarr;**](./06-Advanced-Topics.md)