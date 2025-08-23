# 4. Managing Table Relationships

Your tables rarely live in isolation. A well-designed database relies on clearly defined **relationships** to connect data and maintain its integrity. This is achieved using **foreign keys**.

A foreign key is a rule you create that links a column in one table to a primary key in another. This guide will teach you how to master these relationships using the library's powerful tools.

### Why Are Foreign Keys So Important?

Imagine you have a `books` table and an `authors` table. A foreign key can enforce these critical rules:
1.  You cannot add a book with an `author_id` that doesn't exist in the `authors` table.
2.  You can control what happens to an author's books if that author is ever deleted from the database.

This prevents "orphaned" records and ensures your data remains consistent and reliable.

---

## Part 1: Defining Relationships on Table Creation

The most common time to create a relationship is when you are defining a "child" table that depends on a "parent" table.

Let's continue our example with `Authors_Table` and `Books_Table`. Every book must have an author.

**`src/Database/Tables/Books_Table.php`**
```php
protected function migrate_to_10001(): bool
{
    global $wpdb;

    return $this->create_table(function (Create_Table_Schema $schema) use ($wpdb) {
        $schema->id(); // Primary key for the books table.
        $schema->string('title')->index();

        // Step 1: Define the column that will store the link to the authors table.
        // It must be an unsigned integer to match the parent's `id` column.
        $schema->big_integer('author_id')->unsigned();

        // Step 2: Add the foreign key constraint using a fluent chain.
        $schema->add_foreign_key('author_id')
               ->references(
                   $wpdb->prefix . 'my_plugin_authors', // The FULL name of the parent table
                   'id'                                 // The primary key of the parent table
               )
               ->on_delete('CASCADE'); // Defines what happens when an author is deleted.

        return $schema;
    });
}
```

#### Breaking Down the `add_foreign_key` Chain

-   `->add_foreign_key('author_id')`: You start by specifying the column in the current (`books`) table that will hold the reference.
-   `->references('table_name', 'column_name')`: Next, you specify the parent table and column it is linking to. **Crucially, you must use the full, prefixed table name here.**
-   `->on_delete('ACTION')`: This is the **referential action**. It's a rule that tells the database what to do if the parent row (the author) is deleted.

#### Understanding Referential Actions

This is the most powerful part of a foreign key. You can control the outcome when a parent row is deleted or updated.

| Action | `on_delete()` Description |
| :--- | :--- |
| `CASCADE` | **(Common)** If the parent row is deleted, automatically delete all child rows that reference it. (e.g., deleting an author automatically deletes all their books). |
| `SET NULL`| **(Common)** If the parent row is deleted, set the foreign key column in the child rows to `NULL`. The local column must be defined as `->nullable()` for this to work. |
| `RESTRICT`| **(Default & Safest)** Prevents the parent row from being deleted if any child rows are still referencing it. An error will be thrown. |
| `NO ACTION`| Similar to `RESTRICT` in most database systems. |

You can also specify an `->on_update('ACTION')` rule, which defines what happens if the parent row's primary key value changes (a very rare event).

---

## Part 2: Managing Relationships in Migrations

You can also add or drop foreign key relationships on tables that already exist.

### `add_foreign_key()`

Adds a foreign key constraint to an existing table. The column must already exist.

#### Signature
`add_foreign_key(string $column, string $ref_table, string $ref_column, ?string $constraint_name = null, string $on_delete = 'RESTRICT', string $on_update = 'RESTRICT'): bool`

#### Example
Let's add our author relationship to a `Books_Table` that was created without one.
```php
// In Books_Table.php...
protected function migrate_to_10002(): bool
{
    global $wpdb;

    // First, we need to ensure the `author_id` column exists.
    $this->add_column('author_id', 'BIGINT UNSIGNED NULL', 'title');

    // Now, we can add the foreign key constraint to it.
    $this->add_foreign_key(
        'author_id',                               // Local column
        $wpdb->prefix . 'my_plugin_authors',       // Foreign table
        'id',                                      // Foreign column
        null,                                      // Let the library name the constraint
        'SET NULL'                                 // ON DELETE action
    );
    
    return true;
}
```

### `drop_foreign_key()`

Removes a foreign key constraint from a table. To do this, you need the unique name of the constraint.

#### Signature
`drop_foreign_key(string $constraint_name): bool`

#### Finding the Constraint Name
If you didn't provide a custom name when creating the key, the library generates one for you using the pattern: `fk_{table_name}_{column_name}`. For our example, the name would be `fk_books_author_id`.

#### Example
```php
protected function migrate_to_10003(): bool
{
    // Drop the foreign key constraint using its auto-generated name.
    $this->drop_foreign_key('fk_books_author_id');
    
    return true;
}
```

---

## Part 3: The "By Reference" Helper (Recommended)

To avoid hardcoding table and column names (which can lead to typos), the library provides a cleaner and safer method that uses your `Table` objects directly.

### `add_foreign_key_by_reference()`

This is the **recommended way** to manage relationships between tables defined with this library. It reads the table name, foreign key name, and primary key name directly from the parent `Table` object.

#### Signature
`add_foreign_key_by_reference(Table_Interface $parent_table, string $on_delete = 'RESTRICT', string $on_update = 'RESTRICT', ?string $constraint_name = null): bool`

#### Example
Here is how you would use it inside a migration. Notice how much cleaner it is.
```php
// In Books_Table.php...
protected function migrate_to_10004(): bool
{
    // You'll need an instance of the parent table.
    $authors_table = new Authors_Table($this->wpdb);
    
    // Add the foreign key by simply passing the parent table object.
    $this->add_foreign_key_by_reference(
        $authors_table,
        'CASCADE' // on_delete action
    );
        
    return true;
}
```
This method is less error-prone and automatically adapts if you ever change the parent table's name or primary key, making your code more maintainable.

---

### What's Next?

You now have a complete understanding of how to build a relational database. The next guide will cover powerful shortcuts and helpers, including the `Meta_Table` class, which automates the creation of a very common type of one-to-many relationship.

-   [**Next: Shortcuts and Helpers &rarr;**](./05-Shortcuts-and-Helpers.md)