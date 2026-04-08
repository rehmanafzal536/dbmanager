# Laravel DB Manager

A powerful, zero-config database manager for Laravel applications. Works with **SQLite** and **MySQL / MariaDB** automatically — install it and visit `/dbmanager`.

Think phpMyAdmin, but built into your Laravel app.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.1 or higher |
| Laravel | 10.x, 11.x, or 12.x |
| SQLite | 3.25+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |

---

## Installation

```bash
composer require rehmanafzal/dbmanager
```

The package auto-discovers via Laravel's package discovery. No service provider registration, no route files to edit, no migrations to run.

Open your browser:

```
http://your-app.test/dbmanager
```

---

## Default Login

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `secret` |

> Change these immediately after first login via the **Settings** page.

---

## Configuration

### Option 1 — Environment variables (recommended)

Add to your `.env` file:

```env
DBMANAGER_USERNAME=admin
DBMANAGER_PASSWORD=your_secure_password
```

### Option 2 — Publish the config file

```bash
php artisan vendor:publish --tag=dbmanager-config
```

This creates `config/dbmanager.php`:

```php
return [
    'username'    => env('DBMANAGER_USERNAME', 'admin'),
    'password'    => env('DBMANAGER_PASSWORD', 'secret'),
    'prefix'      => 'dbmanager',
    'session_key' => 'dbmanager_authenticated',
];
```

### Option 3 — Change credentials from the UI

Go to `/dbmanager/settings` → Change Login Credentials. The new credentials are written directly to your `.env` file.

---

## Features

### Dashboard
- Stats: total tables, total rows, database size, driver
- Full table list with row counts
- One-click browse, structure, export, drop per table
- Backup and restore the entire database

---

### Browse & Data Management

**Inline cell editing (like phpMyAdmin)**
- Double-click any cell to edit it in place
- Smart input types based on column type:
  - `DATETIME` / `TIMESTAMP` → native datetime picker
  - `DATE` → date picker
  - `TIME` → time picker
  - `ENUM(...)` → dropdown with all enum values
  - `TINYINT(1)` / `BOOLEAN` → dropdown: NULL / 0 (false) / 1 (true)
  - `INT` / `DECIMAL` / `FLOAT` → number input
  - `TEXT` / `BLOB` / `JSON` → textarea
  - Everything else → text input
- Press **Enter** or click **✓ Save** to save via AJAX
- Press **Escape** to cancel
- Green toast on success, red on error

**Bulk row actions**
- Checkbox on every row + Select All
- **Edit Selected Rows** → opens a full bulk edit page showing all selected rows as editable forms with smart inputs per column type
- **Delete Selected** → deletes all selected rows in one query with confirmation

**Other data features**
- Paginated table data (25 / 50 / 100 / 250 rows per page)
- Search across all columns simultaneously
- Sort by any column (ascending / descending)
- Insert row, edit row, delete row
- Import CSV into any table
- Export table as CSV or SQL dump
- Truncate table

---

### Create Table

Full phpMyAdmin-style column definition grid:

| Column | Description |
|---|---|
| Name | Column name |
| Type | Grouped dropdown: Numeric, String, Date/Time, Binary, Other |
| Length / Values | Auto-disabled for types that don't need it. Shows `'a','b','c'` hint for ENUM/SET |
| Default | None / NULL / Custom value / CURRENT_TIMESTAMP / Empty string |
| Not Null | Checkbox |
| Unique | Checkbox (creates a UNIQUE index) |
| Auto Inc | Checkbox (MySQL: AUTO_INCREMENT PRIMARY KEY) |
| PK | Checkbox (marks as PRIMARY KEY) |

- Set the number of columns and click **Go** to build the grid
- Click **+ Add Column** to add more rows
- `id`, `created_at`, `updated_at` are added automatically

---

### Table Structure

- View all columns: name, type, length, default, not null, key
- **Edit column** — rename, change type, default, not null via modal
- **Rename column** — dedicated rename modal
- **Drop column** — with confirmation
- **Bulk drop** — select multiple columns and drop at once
- **Add multiple columns at once** — same grid as Create Table (Name, Type, Length, Default, Not Null, Unique, Auto Inc, Position)
  - Position: At End / At Beginning / After [column]
- **Indexes** — list all, add (INDEX / UNIQUE / FULLTEXT), drop
- **Foreign Keys** *(MySQL only)* — list all, add with ON DELETE / ON UPDATE rules, drop

---

### SQL Query Editor

- Full SQL editor with dark code theme
- `Ctrl + Enter` to execute
- Results displayed in a sortable table
- Export query results as CSV
- Quick-fill buttons for common queries (users, products, orders, list tables)

---

### Import & Convert

Upload a database file and it auto-detects the format and converts if needed:

| Upload | Current DB | Action |
|---|---|---|
| `.sqlite` file | MySQL | SQLite → MySQL (types auto-converted) |
| `.sqlite` file | SQLite | Direct import |
| MySQL `.sql` dump | SQLite | MySQL syntax → SQLite syntax |
| MySQL `.sql` dump | MySQL | Direct import |
| SQLite `.sql` dump | MySQL | SQLite syntax → MySQL syntax |

Two import modes:
- **Merge** — insert new rows, skip existing ones (by primary key)
- **Replace** — insert or replace rows with matching primary key

---

### Authentication & Settings

- Session-based login — no database users required
- Credentials stored in `.env`
- Change username and password from the built-in Settings page
- Settings page shows: driver, database name, host (MySQL), Laravel version, PHP version

---

## All Routes

All routes register automatically under `/dbmanager`. Nothing to add to `routes/web.php`.

| Method | URL | Description |
|---|---|---|
| GET | `/dbmanager/login` | Login page |
| GET | `/dbmanager/logout` | Sign out |
| GET | `/dbmanager` | Dashboard |
| GET | `/dbmanager/settings` | Settings & credentials |
| POST | `/dbmanager/settings/update` | Save credentials |
| GET | `/dbmanager/sql` | SQL query editor |
| GET | `/dbmanager/create-table` | Create table form |
| POST | `/dbmanager/create-table` | Save new table |
| DELETE | `/dbmanager/drop-table/{table}` | Drop a table |
| GET | `/dbmanager/import` | Import & convert page |
| POST | `/dbmanager/import/convert` | Run import |
| GET | `/dbmanager/backup` | Download database backup |
| POST | `/dbmanager/restore` | Restore from backup |
| GET | `/dbmanager/table/{table}` | Browse table rows |
| GET | `/dbmanager/table/{table}/structure` | Table structure |
| POST | `/dbmanager/table/{table}/add-column` | Add columns |
| POST | `/dbmanager/table/{table}/rename-column` | Rename a column |
| POST | `/dbmanager/table/{table}/modify-column` | Edit column definition |
| DELETE | `/dbmanager/table/{table}/drop-column` | Drop a column |
| DELETE | `/dbmanager/table/{table}/bulk-drop-columns` | Drop multiple columns |
| POST | `/dbmanager/table/{table}/add-index` | Add an index |
| DELETE | `/dbmanager/table/{table}/drop-index` | Drop an index |
| POST | `/dbmanager/table/{table}/add-foreign-key` | Add a foreign key |
| DELETE | `/dbmanager/table/{table}/drop-foreign-key` | Drop a foreign key |
| GET | `/dbmanager/table/{table}/create` | Insert row form |
| POST | `/dbmanager/table/{table}/store` | Save new row |
| GET | `/dbmanager/table/{table}/edit/{id}` | Edit row form |
| PUT | `/dbmanager/table/{table}/update/{id}` | Save row changes |
| DELETE | `/dbmanager/table/{table}/delete/{id}` | Delete a row |
| POST | `/dbmanager/table/{table}/inline-update` | Inline cell update (AJAX) |
| GET | `/dbmanager/table/{table}/bulk-edit-page` | Bulk edit page |
| POST | `/dbmanager/table/{table}/bulk-update` | Save bulk edit |
| DELETE | `/dbmanager/table/{table}/bulk-delete` | Bulk delete rows |
| DELETE | `/dbmanager/table/{table}/truncate` | Truncate table |
| GET | `/dbmanager/table/{table}/export/csv` | Export as CSV |
| GET | `/dbmanager/table/{table}/export/sql` | Export as SQL |
| POST | `/dbmanager/table/{table}/import/csv` | Import CSV |

---

## Database Support Matrix

| Feature | SQLite | MySQL / MariaDB |
|---|---|---|
| Browse & inline edit | ✅ | ✅ |
| Add / drop columns | ✅ | ✅ |
| Rename column | ✅ (3.25+) | ✅ |
| Modify column type | ⚠️ Rename only | ✅ Full CHANGE |
| Column position (FIRST / AFTER) | ❌ | ✅ |
| Auto Increment | ✅ | ✅ |
| Indexes | ✅ | ✅ |
| FULLTEXT index | ❌ | ✅ |
| Foreign keys | ❌ | ✅ |
| Backup | `.sqlite` download | `.sql` dump |
| Restore | `.sqlite` upload | `.sql` upload |
| Import from SQLite file | ✅ | ✅ |
| Import from SQL dump | ✅ | ✅ |

---

## Publish Views

To customize the UI:

```bash
php artisan vendor:publish --tag=dbmanager-views
```

Views are copied to `resources/views/vendor/dbmanager/`.

---

## Upgrade

```bash
composer update rehmanafzal/dbmanager
php artisan view:clear
php artisan config:clear
```

---

## Security

This package is designed for **development and internal tools**.

- Do **not** expose `/dbmanager` on a public production server without additional protection
- Always change the default `admin` / `secret` credentials
- Consider IP restriction or wrapping routes in your app's `auth` middleware

---

## License

MIT — free to use in personal and commercial projects.
