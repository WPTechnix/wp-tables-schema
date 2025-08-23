# 3. Evolving Tables with Migrations

Your plugin will grow, and your database will need to change with it. **Migrations** are a safe and organized way to alter your database tables *after* they have been created. Instead of writing raw `ALTER TABLE` SQL queries, you will use the library's clear and reliable helper methods.

---

### The Migration Golden Rule

> **Never edit a migration method after it has been released.**

Once your plugin is in the wild, you must assume that the migration has already run on a user's site. If you change it, that user will not get the new changes, leading to errors. To make a change, you must always create a **new** migration.

### The Migration Workflow

The process for changing an existing table is simple and methodical:

1.  **Increment the Schema Version**: In your `Table` class, find the `$schema_version` property and increase its value by one. For example, if it's `10001`, change it to `10002`.
2.  **Create a New Migration Method**: In the same class, add a new protected method. The method name **must** match the new version number: `protected function migrate_to_10002(): bool`.
3.  **Implement the Changes**: Inside this new method, use the library's helper methods (`add_column`, `drop_index`, etc.) to make your desired changes.

The library handles the rest. During your plugin's activation, it will see that the database is at an older version and automatically run your new migration method to bring it up to date.

---

### Modifying Columns

The `Table` class provides several methods for altering columns. You call these from within your new migration method.

#### Adding a Column

This is the most common migration. Use `add_column()` to add a new column to the table.

-   **`add_column(string $column_name, string $sql_definition, ?string $after_column = null)`**

**Example**: Let's add a `status` column to our `Tasks` table.

```php
// In Tasks_Table.php...

// 1. First, update the version number at the top of the class.
protected int $schema_version = 10002;

// 2. Then, add the new migration method.
protected function migrate_to_10002(): bool
{
    // Adds a new VARCHAR(20) column named 'status', places it after the
    // 'priority' column, and gives it a default value.
    $this->add_column('status', "VARCHAR(20) NOT NULL DEFAULT 'pending'", 'priority');
    
    return true; // Return true to confirm the migration was successful.
}
```

#### Dropping a Column

To permanently remove a column and all its data, use `drop_column()`. **Warning:** This is a destructive action and cannot be undone.

-   **`drop_column(string $column_name)`**

**Example**:
```php
protected function migrate_to_10003(): bool
{
    $this->drop_column('some_old_column');
    return true;
}
```

#### Renaming a Column

Use `rename_column()` if you only need to change a column's name without altering its type or attributes.

-   **`rename_column(string $old_name, string $new_name)`**

**Example**: Let's rename our `title` column to `task_name`.
```php
protected function migrate_to_10004(): bool
{
    $this->rename_column('title', 'task_name');
    return true;
}
```

#### Modifying a Column's Definition

Use `modify_column()` when you need to change a column's data type, length, or other attributes.

-   **`modify_column(string $column_name, string $new_sql_definition)`**

**Example**: Let's change our `status` column to allow longer values.
```php
protected function migrate_to_10005(): bool
{
    // You must provide the *entire* new definition for the column.
    $this->modify_column('status', "VARCHAR(30) NOT NULL DEFAULT 'pending'");
    return true;
}
```

---

### Modifying Indexes and Keys

You can also add or remove indexes in a migration.

#### Adding an Index

Use `add_index()` or `add_unique_key()` to add a new index to an existing column.

-   **`add_index(string|array $columns, string $type = 'INDEX', ?string $name = null)`**

**Example**: After adding our `status` column, let's add an index to it for faster queries.
```php
protected function migrate_to_10006(): bool
{
    // Adds a standard index to the 'status' column.
    $this->add_index('status');
    return true;
}
```

#### Dropping an Index

To remove an index, you need its name. If you didn't specify a name when you created it, the library generated one based on a predictable pattern (e.g., `idx_{table_name}_{column_name}`).

-   **`drop_index(string $index_name)`**

**Example**:
```php
protected function migrate_to_10007(): bool
{
    // The library would have named the index on the `status` column `idx_tasks_status`.
    $this->drop_index('idx_tasks_status');
    return true;
}```

---

### What's Next?

You are now fully equipped to create tables and safely evolve their structure over time. However, tables rarely exist in isolation. The next crucial concept is learning how to formally connect them using foreign keys to ensure your data remains consistent and reliable.

-   [**Next: Managing Table Relationships &rarr;**](./04-Managing-Table-Relationships.md)