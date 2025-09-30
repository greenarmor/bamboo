# Database setup wizard (`database.setup`)

The `database.setup` command walks you through configuring Bamboo's database layer without editing PHP configuration files by hand. It is designed for greenfield projects and local development sandboxes where you want to iterate on schema design quickly.

## Prerequisites

- Composer dependencies installed (`composer install`).
- Write access to `.env`, `etc/database.php`, and the chosen database location (for example the SQLite file path).
- For MySQL or PostgreSQL connections, ensure the server is reachable from your shell session.

## Running the wizard

Execute the command from the project root:

```bash
php bin/bamboo database.setup
```

The wizard prints a banner and then prompts for:

1. **Driver selection** – choose between `mysql`, `pgsql`, or `sqlite`. Hitting enter accepts the default derived from your existing `.env` file.
2. **Connection details** – depending on the driver you provide host, port, database name, username, and password. For SQLite you provide a file path (relative paths are resolved against the project root and directories are created automatically).
3. **Table definitions** – you can loop through one or more tables. For each table:
   - Enter the table name.
   - Define one or more columns. Supported column types mirror Laravel's schema builder basics (`increments`, `integer`, `bigInteger`, `string`, `text`, `boolean`, `timestamp`). For non-auto-increment columns you decide whether the column allows `NULL` and optionally set a default value.
   - Optionally seed the table. The wizard asks for values for each non-auto-increment column and lets you add multiple rows before moving on.

Answering "no" to the table or seed prompts exits that portion of the workflow. You can re-run the command at any time; existing tables are detected and skipped, and seeding is only performed when the target table is empty.

## What the command changes

- Updates `.env` with the selected `DB_*` values, creating the file if necessary.
- Rewrites `etc/database.php` so `database.default` points at the chosen connection and the `connections` array contains your new settings.
- Verifies the connection by instantiating `Illuminate\Database\Capsule\Manager` and attempting to obtain a PDO handle.
- Creates each requested table and inserts seed rows when the table is empty.

The command prints messages for every action (connection verification, table creation, seed insertions, or skip notices) and exits with status `0` on success. Failures (such as connection errors) emit the error message to stderr and exit with status `1` so automation can detect misconfiguration.

## Next steps

After running the wizard you can:

- Use `php artisan migrate` equivalents or manual schema builders if you need more advanced column types – the wizard is intended as a bootstrapper.
- Update your application code to consume the generated tables (for example, Eloquent models or query builder calls).
- Commit the updated `.env` template values to your documentation (but avoid committing actual credentials) and check the generated schema into migration scripts as needed.

For additional command contract details see the [CLI reference entry](./README.md#database.setup).
