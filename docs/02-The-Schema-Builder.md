# 2. The Schema Builder

Now that you know how to create a basic table, let's explore the heart of this library: the fluent `Create_Table_Schema` builder. This tool gives you an expressive, easy-to-read API for defining every column, index, and option for your table right within your PHP code.

You access the schema builder inside the `create_table()` method in your migration:

```php
protected function migrate_to_10001(): bool
{
    return $this->create_table(function (Create_Table_Schema $schema) {
        // You'll define your entire table structure here using the $schema object.
        return $schema;
    });
}
```

---

### Defining Columns

The schema builder offers a wide variety of methods that correspond to different SQL data types.

#### Numeric Types

| Method | SQL Type | Description |
| :--- | :--- | :--- |
| `->big_integer($name)` | `BIGINT` | For very large whole numbers. |
| `->integer($name)` | `INT` | A standard 4-byte integer. |
| `->medium_integer($name)` | `MEDIUMINT` | A 3-byte integer. |
| `->small_integer($name)` | `SMALLINT` | A 2-byte integer. |
| `->tiny_integer($name)` | `TINYINT` | A 1-byte integer, great for flags. |
| `->boolean($name)` | `TINYINT(1)` | A specialized tiny integer for true/false values. |
| `->decimal($name, $p, $s)`| `DECIMAL(p, s)` | For precise numbers with a fixed decimal point, ideal for currency. |
| `->float($name)` | `FLOAT` | For single-precision floating-point numbers. |
| `->double($name)` | `DOUBLE` | For double-precision floating-point numbers. |

**Example:**
```php
$schema->integer('order_count');
$schema->decimal('price', 10, 2); // Can store a value like 12345678.99
```

#### String & Text Types

| Method | SQL Type | Description |
| :--- | :--- | :--- |
| `->string($name, $len = 191)` | `VARCHAR(len)` | For variable-length strings. The default length is ideal for indexed columns. |
| `->char($name, $len = 1)` | `CHAR(len)` | For fixed-length strings, like country codes. |
| `->text($name)` | `TEXT` | For short-form text content (up to ~64KB). |
| `->medium_text($name)` | `MEDIUMTEXT` | For longer articles or content (up to ~16MB). |
| `->long_text($name)` | `LONGTEXT` | For very large text content (up to ~4GB). |

**Example:**
```php
$schema->string('customer_email', 100);
$schema->long_text('product_description');
```

#### Date & Time Types

| Method | SQL Type | Description |
| :--- | :--- | :--- |
| `->datetime($name)` | `DATETIME` | For storing a specific date and time. |
| `->date($name)` | `DATE` | For storing dates only (no time). |
| `->time($name)` | `TIME` | For storing times only (no date). |
| `->timestamp($name)` | `TIMESTAMP` | Similar to `DATETIME` but often used for tracking record changes. |
| `->year($name)` | `YEAR` | For storing a 4-digit year. |

**Example:**
```php
$schema->date('start_date');
$schema->datetime('appointment_time');
```

---

### Applying Column Modifiers

After choosing a column's type, you can chain **modifier methods** to add attributes and constraints. Think of these as adjectives that describe the column.

-   `->nullable()`: Allows the column to store `NULL` values. By default, columns are `NOT NULL`.
    ```php
    $schema->string('middle_name')->nullable();
    ```

-   `->default($value)`: Sets a default value that the database will use if one isn't provided.
    ```php
    $schema->integer('vote_count')->default(0);
    $schema->string('status')->default('pending');
    ```

-   `->unsigned()`: For numeric types, this prevents negative values and doubles the maximum positive value.
    ```php
    $schema->integer('user_id')->unsigned();
    ```

-   `->comment($text)`: Adds a descriptive comment to the column in the database schema, which is helpful for developers.
    ```php
    $schema->boolean('is_active')->comment('1 for active, 0 for inactive.');
    ```

---

### Defining Keys and Indexes

Indexes are crucial for fast database lookups. The schema builder makes adding them simple.

#### Primary Keys

Every table should have a primary key to uniquely identify each row. The library provides a convenient shortcut for the most common type of primary key.

-   `->id($column_name = 'id')`: This powerful macro creates a `BIGINT UNSIGNED` column that is an `AUTO_INCREMENT PRIMARY KEY`.

**Example:**
```php
$schema->id(); // Creates the 'id' column.
$schema->id('task_id'); // Creates a primary key named 'task_id' with auto-increment.
```

#### Single-Column Indexes

For simple indexes on a single column, you can chain a method directly onto the column definition.

-   `->index()`: Adds a standard, non-unique index for fast searching.
-   `->unique()`: Adds a unique index, ensuring no two rows have the same value in this column.

**Example:**
```php
// An index makes searching for users by email much faster.
$schema->string('email')->index();

// A unique index prevents duplicate usernames.
$schema->string('username')->unique();
```

#### Composite (Multi-Column) Indexes

Sometimes you need an index that spans multiple columns. For this, you use dedicated schema methods.

-   `->add_index(['column1', 'column2'], 'optional_index_name')`
-   `->add_unique_key(['column1', 'column2'], 'optional_index_name')`

**Scenario**: Imagine you are storing geographic data. You would often search for a `latitude` and `longitude` pair together. A composite index makes this much faster.

```php
$schema->decimal('latitude', 10, 7);
$schema->decimal('longitude', 10, 7);

// Add an index on both columns for efficient location lookups.
$schema->add_index(['latitude', 'longitude']);
```

---

### What's Next?

You now have all the tools to design the perfect schema for a brand-new table. But what happens a year from now when you need to add a new column or index? The next guide will teach you how to safely modify your tables over time using migrations.

-   [**Next: Evolving Tables with Migrations &rarr;**](./03-Evolving-Tables-with-Migrations.md)