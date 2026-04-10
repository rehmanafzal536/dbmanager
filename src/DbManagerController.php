<?php

namespace Devtoolkit\DbManager;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DbManagerController extends Controller
{
    private function pdo(): \PDO
    {
        $default = config('database.default');
        if ($default === 'nativephp' || str_contains($default, 'nativephp')) {
            return DB::connection('nativephp')->getPdo();
        }
        return DB::connection()->getPdo();
    }

    private function driver(): string
    {
        $default = config('database.default');
        if ($default === 'nativephp' || str_contains($default, 'nativephp')) {
            return 'sqlite';
        }
        return $default;
    }

    private function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
    }

    private function isMysql(): bool
    {
        return in_array($this->driver(), ['mysql', 'mariadb']);
    }

    // ── Public accessor for other controllers ─────────────────────────────────
    public function getTablesPublic(): array { return $this->allTables(); }

    private function allTables(): array
    {
        $pdo = $this->pdo();
        $result = [];

        if ($this->isSqlite()) {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                          ->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        }

        foreach ($tables as $t) {
            try {
                $result[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            } catch (\Exception $e) {
                $result[$t] = 0;
            }
        }
        return $result;
    }

    private function tableColumns(string $table): array
    {
        if ($this->isSqlite()) {
            return $this->pdo()->query("PRAGMA table_info(\"{$table}\")")->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $cols = $this->pdo()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            // Convert MySQL format to SQLite-like format
            return array_map(function($col, $idx) {
                return [
                    'cid' => $idx,
                    'name' => $col['Field'],
                    'type' => $col['Type'],
                    'notnull' => $col['Null'] === 'NO' ? 1 : 0,
                    'dflt_value' => $col['Default'],
                    'pk' => $col['Key'] === 'PRI' ? 1 : 0,
                ];
            }, $cols, array_keys($cols));
        }
    }

    private function pk(string $table): string
    {
        $cols = $this->tableColumns($table);
        foreach ($cols as $c) {
            if ($c['pk']) return $c['name'];
        }
        return 'id';
    }

    // ── Overview ─────────────────────────────────────────────────────────────
    public function index()
    {
        $tables = $this->allTables();
        
        if ($this->isSqlite()) {
            $dbPath = config('database.connections.sqlite.database');
            $dbSize = file_exists($dbPath) ? round(filesize($dbPath)/1024/1024, 2).' MB' : '?';
        } else {
            $dbName = config('database.connections.mysql.database');
            $size = DB::select("SELECT SUM(data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = ?", [$dbName]);
            $dbSize = $size ? round($size[0]->size/1024/1024, 2).' MB' : '?';
        }
        
        return view('dbmanager::index', compact('tables','dbSize'));
    }

    // ── Browse table ──────────────────────────────────────────────────────────
    public function table(Request $request, string $table)
    {
        $pdo     = $this->pdo();
        $page    = max(1,(int)$request->get('page',1));
        $perPage = (int)$request->get('per_page',50);
        $search  = $request->get('search','');
        $orderBy = $request->get('order_by','');
        $orderDir= strtoupper($request->get('order_dir','ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $cols    = $this->tableColumns($table);
        $colNames= array_column($cols, 'name');

        $where = '';
        $params = [];
        if ($search && $colNames) {
            $parts = array_map(fn($c) => "CAST(`{$c}` AS CHAR) LIKE ?", $colNames);
            $where = 'WHERE '.implode(' OR ', $parts);
            $params = array_fill(0, count($colNames), "%{$search}%");
        }

        $orderSql = $orderBy && in_array($orderBy,$colNames) ? "ORDER BY `{$orderBy}` {$orderDir}" : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset  = ($page-1)*$perPage;
        $stmt    = $pdo->prepare("SELECT * FROM `{$table}` {$where} {$orderSql} LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $rows    = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $pages   = max(1,(int)ceil($total/$perPage));
        $tables  = $this->allTables();

        return view('dbmanager::table', compact('table','rows','cols','colNames','total','page','pages','perPage','search','orderBy','orderDir','tables'));
    }
    // ── Structure ─────────────────────────────────────────────────────────────
    public function structure(string $table)
    {
        $cols    = $this->tableColumns($table);
        $indexes = [];

        if ($this->isSqlite()) {
            $raw = $this->pdo()->query("PRAGMA index_list(\"{$table}\")")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($raw as $idx) {
                $idxCols = $this->pdo()->query("PRAGMA index_info(\"{$idx['name']}\")")->fetchAll(\PDO::FETCH_ASSOC);
                $idx['columns'] = array_column($idxCols, 'name');
                $indexes[] = $idx;
            }
        } else {
            $raw = $this->pdo()->query("SHOW INDEX FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($raw as $r) {
                $n = $r['Key_name'];
                if (!isset($grouped[$n])) {
                    $grouped[$n] = ['name' => $n, 'unique' => !$r['Non_unique'], 'origin' => $r['Index_type'], 'columns' => []];
                }
                $grouped[$n]['columns'][] = $r['Column_name'];
            }
            $indexes = array_values($grouped);
        }

        $foreignKeys = $this->getForeignKeys($table);
        $allTables   = array_keys($this->allTables());
        $tables      = $this->allTables();
        $isSqlite    = $this->isSqlite();

        return view('dbmanager::structure', compact('table','cols','indexes','foreignKeys','allTables','tables','isSqlite'));
    }

    // ── Rename column ─────────────────────────────────────────────────────────
    public function renameColumn(Request $request, string $table)
    {
        $old = $request->input('old_name');
        $new = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('new_name'));
        if (!$old || !$new) return back()->with('error', 'Column names required');
        try {
            $this->pdo()->exec("ALTER TABLE `{$table}` RENAME COLUMN `{$old}` TO `{$new}`");
            return back()->with('success', "Column '{$old}' renamed to '{$new}'");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Modify column ─────────────────────────────────────────────────────────
    public function modifyColumn(Request $request, string $table)
    {
        $col     = $request->input('col_name');
        $type    = $request->input('col_type', 'TEXT');
        $default = $request->input('col_default', '');
        $notnull = $request->boolean('col_notnull');
        $newname = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('col_newname', $col));

        try {
            if ($this->isMysql()) {
                $def = "`{$newname}` {$type}";
                if ($notnull && $default !== '') $def .= " NOT NULL DEFAULT '{$default}'";
                elseif ($notnull)                $def .= " NOT NULL";
                elseif ($default !== '')         $def .= " DEFAULT '{$default}'";
                $this->pdo()->exec("ALTER TABLE `{$table}` CHANGE `{$col}` {$def}");
            } else {
                if ($newname !== $col) {
                    $this->pdo()->exec("ALTER TABLE `{$table}` RENAME COLUMN `{$col}` TO `{$newname}`");
                }
                return back()->with('success', "Column renamed. Full type modification requires table rebuild in SQLite.");
            }
            return back()->with('success', "Column '{$col}' modified");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Bulk drop columns ─────────────────────────────────────────────────────
    public function bulkDropColumns(Request $request, string $table)
    {
        $cols = $request->input('columns', []);
        if (empty($cols)) return back()->with('error', 'No columns selected');
        $dropped = 0;
        foreach ($cols as $col) {
            try {
                $this->pdo()->exec("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
                $dropped++;
            } catch (\Exception $e) {}
        }
        return back()->with('success', "{$dropped} column(s) dropped");
    }

    // ── Add index ─────────────────────────────────────────────────────────────
    public function addIndex(Request $request, string $table)
    {
        $cols    = $request->input('index_cols', []);
        $type    = $request->input('index_type', 'INDEX');
        $idxName = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('index_name', 'idx_'.implode('_', $cols)));
        if (empty($cols)) return back()->with('error', 'Select at least one column');
        $colList = implode('`, `', $cols);
        try {
            $this->pdo()->exec("CREATE {$type} INDEX `{$idxName}` ON `{$table}` (`{$colList}`)");
            return back()->with('success', "Index '{$idxName}' created");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Drop index ────────────────────────────────────────────────────────────
    public function dropIndex(Request $request, string $table)
    {
        $name = $request->input('index_name');
        try {
            if ($this->isSqlite()) {
                $this->pdo()->exec("DROP INDEX IF EXISTS `{$name}`");
            } else {
                $this->pdo()->exec("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }
            return back()->with('success', "Index '{$name}' dropped");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Add foreign key (MySQL only) ──────────────────────────────────────────
    public function addForeignKey(Request $request, string $table)
    {
        if ($this->isSqlite()) return back()->with('error', 'Foreign keys via ALTER TABLE are not supported in SQLite');
        $col      = $request->input('fk_col');
        $refTable = $request->input('fk_ref_table');
        $refCol   = $request->input('fk_ref_col', 'id');
        $onDelete = $request->input('fk_on_delete', 'RESTRICT');
        $onUpdate = $request->input('fk_on_update', 'RESTRICT');
        $fkName   = 'fk_'.$table.'_'.$col.'_'.time();
        try {
            $this->pdo()->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$col}`) REFERENCES `{$refTable}` (`{$refCol}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}");
            return back()->with('success', "Foreign key added: {$col} → {$refTable}.{$refCol}");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Drop foreign key (MySQL only) ─────────────────────────────────────────
    public function dropForeignKey(Request $request, string $table)
    {
        if ($this->isSqlite()) return back()->with('error', 'Not supported in SQLite');
        $name = $request->input('fk_name');
        try {
            $this->pdo()->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            return back()->with('success', "Foreign key '{$name}' dropped");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Get foreign keys ──────────────────────────────────────────────────────
    private function getForeignKeys(string $table): array
    {
        if ($this->isSqlite()) return [];
        try {
            $db = config('database.connections.mysql.database');
            return DB::select("
                SELECT kcu.CONSTRAINT_NAME as name, kcu.COLUMN_NAME as col,
                       kcu.REFERENCED_TABLE_NAME as ref_table, kcu.REFERENCED_COLUMN_NAME as ref_col,
                       rc.DELETE_RULE as on_delete, rc.UPDATE_RULE as on_update
                FROM information_schema.KEY_COLUMN_USAGE kcu
                JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                  ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                  AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ?
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ", [$db, $table]);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── Add column (multi-column phpMyAdmin style) ────────────────────────────
    public function addColumn(Request $request, string $table)
    {
        $cols   = $request->input('cols', []);
        $added  = 0;
        $errors = [];

        // Legacy single-column fallback
        if (empty($cols) && $request->input('col_name')) {
            $cols = [[
                'name'         => $request->input('col_name'),
                'type'         => $request->input('col_type', 'TEXT'),
                'length'       => '',
                'default_type' => $request->input('col_default') !== '' ? 'custom' : 'none',
                'default_val'  => $request->input('col_default', ''),
                'notnull'      => $request->boolean('col_notnull') ? 'on' : '',
                'unique'       => '',
                'auto_inc'     => '',
                'position'     => 'last',
            ]];
        }

        foreach ($cols as $c) {
            if (empty($c['name'])) continue;
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', $c['name']);
            $type = $c['type'] ?? 'TEXT';
            if (!empty($c['length']) && !str_contains($type, '(')) {
                $type .= "({$c['length']})";
            }
            $def = "`{$name}` {$type}";
            if (!empty($c['notnull'])) $def .= ' NOT NULL';
            $defType = $c['default_type'] ?? 'none';
            if ($defType === 'null')              $def .= ' DEFAULT NULL';
            elseif ($defType === 'current_timestamp') $def .= ' DEFAULT CURRENT_TIMESTAMP';
            elseif ($defType === 'custom' && ($c['default_val'] ?? '') !== '') $def .= " DEFAULT '{$c['default_val']}'";
            if (!empty($c['auto_inc']) && $this->isMysql()) $def .= ' AUTO_INCREMENT';

            $posSql = '';
            if ($this->isMysql()) {
                $pos = $c['position'] ?? 'last';
                if ($pos === 'first') $posSql = ' FIRST';
                elseif (str_starts_with($pos, 'after_')) $posSql = " AFTER `".substr($pos, 6)."`";
            }

            try {
                $this->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN {$def}{$posSql}");
                if (!empty($c['unique'])) {
                    try { $this->pdo()->exec("CREATE UNIQUE INDEX `uq_{$table}_{$name}` ON `{$table}` (`{$name}`)"); } catch (\Exception $e) {}
                }
                $added++;
            } catch (\Exception $e) {
                $errors[] = "{$name}: ".$e->getMessage();
            }
        }

        if ($errors) return back()->with('error', implode(' | ', $errors));
        return back()->with('success', "{$added} column(s) added to {$table}");
    }

    // ── Drop column ───────────────────────────────────────────────────────────
    public function dropColumn(Request $request, string $table)
    {
        $col = $request->input('col_name');
        try {
            $this->pdo()->exec("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
            return back()->with('success', "Column '{$col}' dropped");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Create table ──────────────────────────────────────────────────────────
    public function createTable(Request $request)
    {
        $tables = $this->allTables();
        return view('dbmanager::create_table', compact('tables'));
    }

    public function storeTable(Request $request)
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $request->input('table_name'));
        $cols = $request->input('columns', []);

        if ($this->isSqlite()) {
            $defs = ['`id` INTEGER PRIMARY KEY AUTOINCREMENT'];
        } else {
            $defs = ['`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'];
        }

        $pkSet = false;
        foreach ($cols as $c) {
            if (empty($c['name'])) continue;
            $cname = preg_replace('/[^a-zA-Z0-9_]/', '', $c['name']);
            $ctype = $c['type'] ?? 'VARCHAR';

            // Append length/values if provided
            $len = trim($c['length'] ?? '');
            if ($len !== '' && !in_array(strtoupper($ctype), ['TEXT','TINYTEXT','MEDIUMTEXT','LONGTEXT','BLOB','TINYBLOB','MEDIUMBLOB','LONGBLOB','DATE','DATETIME','TIMESTAMP','TIME','YEAR','BOOLEAN','JSON','INT','BIGINT','TINYINT','SMALLINT','MEDIUMINT','FLOAT','DOUBLE'])) {
                $ctype .= "({$len})";
            }

            $def = "`{$cname}` {$ctype}";

            // AUTO_INCREMENT (MySQL only, implies PK)
            if (!empty($c['auto_inc']) && $this->isMysql() && !$pkSet) {
                $def .= ' AUTO_INCREMENT PRIMARY KEY';
                $pkSet = true;
            } elseif (!empty($c['pk']) && !$pkSet) {
                if ($this->isSqlite()) {
                    $def .= ' PRIMARY KEY';
                } else {
                    $def .= ' PRIMARY KEY';
                }
                $pkSet = true;
            }

            // NOT NULL
            if (!empty($c['notnull'])) $def .= ' NOT NULL';

            // DEFAULT
            $defType = $c['default_type'] ?? 'none';
            if ($defType === 'null') {
                $def .= ' DEFAULT NULL';
            } elseif ($defType === 'current_timestamp') {
                $def .= ' DEFAULT CURRENT_TIMESTAMP';
            } elseif ($defType === 'empty') {
                $def .= " DEFAULT ''";
            } elseif ($defType === 'custom' && ($c['default_val'] ?? '') !== '') {
                $escaped = str_replace("'", "''", $c['default_val']);
                $def .= " DEFAULT '{$escaped}'";
            }

            $defs[] = $def;

            // UNIQUE index (added after table creation)
        }

        $defs[] = '`created_at` TIMESTAMP NULL DEFAULT NULL';
        $defs[] = '`updated_at` TIMESTAMP NULL DEFAULT NULL';

        $engine = $this->isMysql() ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $sql = "CREATE TABLE IF NOT EXISTS `{$name}` (".implode(', ', $defs).")$engine";

        try {
            $this->pdo()->exec($sql);

            // Add UNIQUE indexes for columns marked unique
            foreach ($cols as $c) {
                if (empty($c['name']) || empty($c['unique'])) continue;
                $cname = preg_replace('/[^a-zA-Z0-9_]/', '', $c['name']);
                try {
                    $this->pdo()->exec("CREATE UNIQUE INDEX `uq_{$name}_{$cname}` ON `{$name}` (`{$cname}`)");
                } catch (\Exception $e) { /* ignore */ }
            }

            return redirect("/dbmanager/table/{$name}")->with('success', "Table '{$name}' created successfully");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Drop table ────────────────────────────────────────────────────────────
    public function dropTable(string $table)
    {
        try {
            $this->pdo()->exec("DROP TABLE IF EXISTS `{$table}`");
            return redirect('/dbmanager')->with('success', "Table '{$table}' dropped");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Edit row ──────────────────────────────────────────────────────────────
    public function editRow(string $table, $id)
    {
        $pk   = $this->pk($table);
        $stmt = $this->pdo()->prepare("SELECT * FROM `{$table}` WHERE `{$pk}`=?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        $cols = $this->tableColumns($table);
        $tables = $this->allTables();
        return view('dbmanager::edit', compact('table','row','cols','pk','tables'));
    }

    public function updateRow(Request $request, string $table, $id)
    {
        $pk   = $this->pk($table);
        $cols = array_column($this->tableColumns($table), 'name');
        $sets = []; $vals = [];
        foreach ($request->except(['_token','_method']) as $col => $val) {
            if (in_array($col,$cols) && $col !== $pk) {
                $sets[] = "`{$col}`=?";
                $vals[] = $val === '' ? null : $val;
            }
        }
        $vals[] = $id;
        $this->pdo()->prepare("UPDATE `{$table}` SET ".implode(',',$sets)." WHERE `{$pk}`=?")->execute($vals);
        return redirect("/dbmanager/table/{$table}")->with('success','Row updated');
    }

    // ── Delete row ────────────────────────────────────────────────────────────
    public function deleteRow(string $table, $id)
    {
        $pk = $this->pk($table);
        $this->pdo()->prepare("DELETE FROM `{$table}` WHERE `{$pk}`=?")->execute([$id]);
        return back()->with('success','Row deleted');
    }

    // ── Bulk edit page (GET) — shows all selected rows editable ──────────────
    public function bulkEditPage(Request $request, string $table)
    {
        $ids = array_values(array_filter(explode(',', $request->get('ids', ''))));
        if (empty($ids)) {
            return redirect("/dbmanager/table/{$table}")->with('error', 'No rows selected');
        }

        $pk   = $this->pk($table);
        $cols = $this->tableColumns($table);
        $ph   = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->pdo()->prepare("SELECT * FROM `{$table}` WHERE `{$pk}` IN ({$ph})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return redirect("/dbmanager/table/{$table}")->with('error', 'No rows found for the selected IDs');
        }

        $tables = $this->allTables();
        return view('dbmanager::bulk_edit', compact('table', 'rows', 'cols', 'pk', 'tables', 'ids'));
    }

    // ── Bulk update rows (POST) ───────────────────────────────────────────────
    public function bulkUpdateRows(Request $request, string $table)
    {
        $pk      = $this->pk($table);
        $colDefs = $this->tableColumns($table);
        $colNames= array_column($colDefs, 'name');
        // Build a type map for datetime conversion
        $typeMap = array_column($colDefs, 'type', 'name');
        $rows    = $request->input('rows', []);
        $updated = 0;
        foreach ($rows as $id => $data) {
            $sets = []; $vals = [];
            foreach ($data as $col => $val) {
                if (!in_array($col, $colNames) || $col === $pk) continue;
                // Convert datetime-local (T separator) back to MySQL format
                $rawType = strtolower($typeMap[$col] ?? '');
                if ($val && (str_contains($rawType, 'datetime') || str_contains($rawType, 'timestamp'))) {
                    $val = str_replace('T', ' ', $val);
                    if (strlen($val) === 16) $val .= ':00'; // add seconds
                }
                $sets[] = "`{$col}`=?";
                $vals[] = $val === '' ? null : $val;
            }
            if ($sets) {
                $vals[] = $id;
                $this->pdo()->prepare("UPDATE `{$table}` SET ".implode(',', $sets)." WHERE `{$pk}`=?")->execute($vals);
                $updated++;
            }
        }
        return redirect("/dbmanager/table/{$table}")->with('success', "{$updated} row(s) updated");
    }

    // ── Inline update (AJAX double-click cell edit) ───────────────────────────
    public function inlineUpdate(Request $request, string $table)
    {
        $pk    = $this->pk($table);
        $id    = $request->input('pk_val');
        $col   = $request->input('col');
        $val   = $request->input('val');
        $cols  = array_column($this->tableColumns($table), 'name');
        if (!in_array($col, $cols) || $col === $pk) {
            return response()->json(['error' => 'Invalid column'], 422);
        }
        try {
            $this->pdo()->prepare("UPDATE `{$table}` SET `{$col}`=? WHERE `{$pk}`=?")->execute([$val === '' ? null : $val, $id]);
            return response()->json(['success' => true, 'val' => $val]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Bulk delete rows ──────────────────────────────────────────────────────
    public function bulkDeleteRows(Request $request, string $table)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return back()->with('error', 'No rows selected');
        $pk = $this->pk($table);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo()->prepare("DELETE FROM `{$table}` WHERE `{$pk}` IN ({$ph})")->execute($ids);
        return back()->with('success', count($ids).' row(s) deleted');
    }

    // ── Bulk edit rows (single column across multiple rows) ───────────────────
    public function bulkEditRows(Request $request, string $table)
    {
        $ids  = $request->input('ids', []);
        $col  = $request->input('bulk_col');
        $val  = $request->input('bulk_val');
        if (empty($ids) || !$col) return back()->with('error', 'No rows or column selected');
        $pk = $this->pk($table);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$val === '' ? null : $val], $ids);
        $this->pdo()->prepare("UPDATE `{$table}` SET `{$col}`=? WHERE `{$pk}` IN ({$ph})")->execute($params);
        return back()->with('success', count($ids).' row(s) updated');
    }

    // ── Create row ────────────────────────────────────────────────────────────
    public function createRow(string $table)
    {
        $cols   = $this->tableColumns($table);
        $tables = $this->allTables();
        return view('dbmanager::create_row', compact('table','cols','tables'));
    }

    public function storeRow(Request $request, string $table)
    {
        $cols = array_column($this->tableColumns($table), 'name');
        $ins  = []; $vals = [];
        foreach ($request->except(['_token']) as $col => $val) {
            if (in_array($col,$cols)) {
                $ins[]  = "`{$col}`";
                $vals[] = $val === '' ? null : $val;
            }
        }
        $ph = implode(',',array_fill(0,count($ins),'?'));
        $this->pdo()->prepare("INSERT INTO `{$table}` (".implode(',',$ins).") VALUES ({$ph})")->execute($vals);
        return redirect("/dbmanager/table/{$table}")->with('success','Row inserted');
    }

    // ── Truncate ──────────────────────────────────────────────────────────────
    public function truncateTable(string $table)
    {
        if ($this->isSqlite()) {
            $this->pdo()->exec("DELETE FROM `{$table}`");
        } else {
            $this->pdo()->exec("TRUNCATE TABLE `{$table}`");
        }
        return redirect("/dbmanager/table/{$table}")->with('success',"Table '{$table}' truncated");
    }

    // ── Run SQL ───────────────────────────────────────────────────────────────
    public function runSql(Request $request)
    {
        $sql = trim($request->input('sql',''));
        $result = null; $error = null; $affected = null;
        if ($sql) {
            try {
                $pdo  = $this->pdo();
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $result   = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $affected = $stmt->rowCount();
            } catch (\Exception $e) { $error = $e->getMessage(); }
        }
        $tables = $this->allTables();
        return view('dbmanager::sql', compact('sql','result','error','affected','tables'));
    }

    // ── Backup ────────────────────────────────────────────────────────────────
    public function backup()
    {
        if ($this->isSqlite()) {
            $dbPath   = config('database.connections.sqlite.database');
            $filename = 'backup-'.date('Y-m-d_H-i-s').'.sqlite';
            return response()->download($dbPath, $filename);
        } else {
            // For MySQL, export as SQL dump
            $tables = $this->allTables();
            $dump = "-- MySQL Dump\n-- Generated: ".date('Y-m-d H:i:s')."\n\n";
            
            foreach (array_keys($tables) as $table) {
                $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
                $dump .= "\n-- Table: {$table}\n";
                $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $dump .= $createTable[0]->{'Create Table'}.";\n\n";
                
                $rows = DB::table($table)->get();
                foreach ($rows as $row) {
                    $row = (array)$row;
                    $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($row)));
                    $vals = implode(',', array_map(fn($v) => $v===null ? 'NULL' : "'".addslashes($v)."'", array_values($row)));
                    $dump .= "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
                }
                $dump .= "\n";
            }
            
            return response($dump, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename=backup-'.date('Y-m-d_H-i-s').'.sql'
            ]);
        }
    }

    // ── Restore ───────────────────────────────────────────────────────────────
    public function restore(Request $request)
    {
        $file = $request->file('db_file');
        if (!$file) return back()->with('error', 'No file uploaded');

        if ($this->isSqlite()) {
            $dbPath = config('database.connections.sqlite.database');
            $handle = fopen($file->getRealPath(), 'rb');
            $magic  = fread($handle, 16);
            fclose($handle);
            if (strpos($magic, 'SQLite format') === false) {
                return back()->with('error', 'Invalid SQLite file');
            }
            copy($file->getRealPath(), $dbPath);
        } else {
            // For MySQL, execute SQL dump
            $sql = file_get_contents($file->getRealPath());
            DB::unprepared($sql);
        }
        
        return back()->with('success', 'Database restored successfully');
    }

    // ── Export CSV ────────────────────────────────────────────────────────────
    public function exportCsv(string $table)
    {
        $rows = $this->pdo()->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
        $filename = "{$table}_".date('Ymd_His').".csv";
        $headers  = ['Content-Type'=>'text/csv','Content-Disposition'=>"attachment; filename={$filename}"];
        $callback = function() use ($rows) {
            $out = fopen('php://output','w');
            if ($rows) { fputcsv($out, array_keys($rows[0])); }
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    // ── Export SQL ────────────────────────────────────────────────────────────
    public function exportSql(string $table)
    {
        $pdo  = $this->pdo();
        
        if ($this->isSqlite()) {
            $ddl  = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
        } else {
            $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
            $ddl = $createTable[0]->{'Create Table'};
        }
        
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
        $out  = "-- Table: {$table}\n{$ddl};\n\n";
        
        foreach ($rows as $r) {
            $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($r)));
            $vals = implode(',', array_map(fn($v) => $v===null ? 'NULL' : "'".str_replace("'","''",$v)."'", array_values($r)));
            $out .= "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
        }
        
        return response($out,200,['Content-Type'=>'text/plain','Content-Disposition'=>"attachment; filename={$table}.sql"]);
    }

    // ── Import CSV ────────────────────────────────────────────────────────────
    public function importCsv(Request $request, string $table)
    {
        $file = $request->file('csv_file');
        if (!$file) return back()->with('error','No file uploaded');
        
        $handle = fopen($file->getRealPath(),'r');
        $headers = fgetcsv($handle);
        $count = 0;
        $pdo = $this->pdo();
        $cols = array_column($this->tableColumns($table),'name');
        
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            $ins  = []; $vals = [];
            foreach ($data as $k => $v) {
                if (in_array($k,$cols)) { $ins[] = "`{$k}`"; $vals[] = $v === '' ? null : $v; }
            }
            if ($ins) {
                $ph = implode(',',array_fill(0,count($ins),'?'));
                $pdo->prepare("INSERT INTO `{$table}` (".implode(',',$ins).") VALUES ({$ph})")->execute($vals);
                $count++;
            }
        }
        fclose($handle);
        return back()->with('success',"{$count} rows imported from CSV");
    }

    // ── Import & Convert page ─────────────────────────────────────────────────
    public function importPage()
    {
        $tables = $this->allTables();
        $currentDriver = $this->driver();
        return view('dbmanager::import', compact('tables', 'currentDriver'));
    }

    // ── Import & Convert (SQLite ↔ MySQL auto-detect & convert) ──────────────
    public function importConvert(Request $request)
    {
        $file = $request->file('db_file');
        if (!$file) return back()->with('error', 'No file uploaded');

        $path    = $file->getRealPath();
        $content = file_get_contents($path);
        $mode    = $request->input('mode', 'merge'); // merge | replace

        // ── Detect source format ──────────────────────────────────────────────
        $isSqliteFile = (substr($content, 0, 15) === 'SQLite format 3');
        $isSqlDump    = !$isSqliteFile && (
            stripos($content, 'CREATE TABLE') !== false ||
            stripos($content, 'INSERT INTO')  !== false
        );

        if (!$isSqliteFile && !$isSqlDump) {
            return back()->with('error', 'Unrecognized file format. Upload a .sqlite file or a .sql dump.');
        }

        $log    = [];
        $dstPdo = $this->pdo();
        $dstPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            // ── REPLACE mode: wipe all existing tables first ──────────────────
            if ($mode === 'replace') {
                $existingTables = $this->allTables();
                if ($this->isSqlite()) {
                    $dstPdo->exec('PRAGMA foreign_keys = OFF');
                } else {
                    $dstPdo->exec('SET FOREIGN_KEY_CHECKS=0');
                }
                foreach (array_keys($existingTables) as $t) {
                    $dstPdo->exec("DROP TABLE IF EXISTS " . ($this->isSqlite() ? "\"{$t}\"" : "`{$t}`"));
                    $log[] = "🗑 Dropped table: {$t}";
                }
                // FK checks stay OFF until after all tables are created
            }

            if ($isSqliteFile) {
                // ── Source: SQLite binary file ────────────────────────────────
                $srcPdo = new \PDO("sqlite:{$path}");
                $srcPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                $srcTables = $srcPdo->query(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                )->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($srcTables as $table) {
                    $srcCols = $srcPdo->query("PRAGMA table_info(\"{$table}\")")->fetchAll(\PDO::FETCH_ASSOC);
                    $rows    = $srcPdo->query("SELECT * FROM \"{$table}\"")->fetchAll(\PDO::FETCH_ASSOC);

                    $this->ensureTableExists($dstPdo, $table, $srcCols);
                    $dstColNames = $this->getDestColNames($dstPdo, $table);

                    if (empty($rows)) { $log[] = "⏭ {$table}: empty, skipped"; continue; }

                    $inserted = 0; $skipped = 0; $errMsgs = [];
                    foreach ($rows as $row) {
                        $ins = []; $vals = [];
                        foreach ($row as $col => $val) {
                            if (in_array($col, $dstColNames)) {
                                $ins[]  = $this->isSqlite() ? "\"{$col}\"" : "`{$col}`";
                                $vals[] = $val;
                            }
                        }
                        if (!$ins) continue;
                        $ph  = implode(',', array_fill(0, count($ins), '?'));
                        $tbl = $this->isSqlite() ? "\"{$table}\"" : "`{$table}`";
                        $sql = $this->isSqlite()
                            ? "INSERT OR IGNORE INTO {$tbl} (".implode(',', $ins).") VALUES ({$ph})"
                            : "INSERT IGNORE INTO {$tbl} (".implode(',', $ins).") VALUES ({$ph})";
                        try {
                            $dstPdo->prepare($sql)->execute($vals);
                            $inserted++;
                        } catch (\Exception $e) {
                            $skipped++;
                            $errMsgs[] = $e->getMessage();
                        }
                    }
                    $msg = "✅ {$table}: {$inserted} inserted, {$skipped} skipped";
                    if ($errMsgs) $msg .= ' — last error: '.end($errMsgs);
                    $log[] = $msg;
                }

            } else {
                // ── Source: SQL dump ──────────────────────────────────────────
                $isMysqlDump = stripos($content, 'ENGINE=') !== false
                    || stripos($content, 'AUTO_INCREMENT') !== false
                    || stripos($content, '/*!') !== false;

                $statements = $this->parseSqlStatements($content);
                $inserted = 0; $created = 0; $errors = []; $skipped = 0;
                $skippedStatements = []; // Track skipped statements with reasons
                $autoCreatedTables = []; // Track auto-created tables

                // Disable FK checks for the whole import
                if ($this->isSqlite()) {
                    $dstPdo->exec('PRAGMA foreign_keys = OFF');
                } else {
                    $dstPdo->exec('SET FOREIGN_KEY_CHECKS=0');
                }

                // Execute CREATE TABLE statements first
                foreach ($statements as $stmt) {
                    $trimmedStmt = trim($stmt);
                    if (preg_match('/^INSERT\s+(?:OR\s+\w+\s+)?INTO\s+[`"]?(\w+)[`"]?/i', $trimmedStmt, $m)) {
                        $referencedTables[$m[1]] = true;
                    }
                }

                // Execute CREATE TABLE statements first
                foreach ($statements as $idx => $stmt) {
                    $originalStmt = trim($stmt);
                    $trimmedStmt = trim($stmt);
                    if (empty($trimmedStmt)) continue;

                    if (preg_match('/^CREATE\s+TABLE/i', $trimmedStmt)) {
                        // Convert syntax if needed
                        if ($isMysqlDump && $this->isSqlite()) {
                            $trimmedStmt = $this->mysqlToSqlite($trimmedStmt);
                        } elseif (!$isMysqlDump && $this->isMysql()) {
                            $trimmedStmt = $this->sqliteToMysql($trimmedStmt);
                        }

                        if (empty($trimmedStmt)) {
                            $skipped++;
                            $skippedStatements[] = "❌ CREATE TABLE: Conversion resulted in empty statement";
                            continue;
                        }

                        if (str_starts_with($trimmedStmt, '__DROP__')) {
                            if (preg_match('/^__DROP__(\w+)__(.+)$/s', $trimmedStmt, $parts)) {
                                try { $dstPdo->exec("DROP TABLE IF EXISTS \"{$parts[1]}\""); } catch (\Exception $e) {}
                                $trimmedStmt = $parts[2];
                            }
                        }

                        try {
                            $dstPdo->exec($trimmedStmt);
                            $created++;
                        } catch (\Exception $e) {
                            $errors[] = substr($e->getMessage(), 0, 120) . ' | SQL: ' . substr($trimmedStmt, 0, 150);
                        }
                    } elseif (!preg_match('/^(INSERT|SELECT|UPDATE|DELETE|DROP|ALTER|PRAGMA|BEGIN|COMMIT|ROLLBACK|SET|USE|LOCK|UNLOCK|START|\/\*|--)/i', $trimmedStmt)) {
                        // Track other non-recognized statements as skipped
                        if ($trimmedStmt && !preg_match('/^(SET\s|LOCK\s|UNLOCK\s|START|USE\s|--|\/\*|/*!)/i', $trimmedStmt)) {
                            // Only log if it looks like it might be important
                            if (strlen($trimmedStmt) > 20) {
                                $skipped++;
                                $skippedStatements[] = "⏭ " . substr($trimmedStmt, 0, 80) . "...";
                            }
                        }
                    }
                }

                // Second pass: execute INSERT statements
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (empty($stmt)) continue;

                    // Skip CREATE TABLE (already processed)
                    if (preg_match('/^CREATE\s+TABLE/i', $stmt)) {
                        continue;
                    }

                    $isInsert = preg_match('/^INSERT\s+(?:OR\s+\w+\s+)?(?:INTO\s+)?/i', $stmt, $m);

                    // For INSERT statements, use prepared statements to handle complex data properly
                    if ($isInsert) {
                        // Before INSERT, ensure table exists
                        if (preg_match('/^INSERT\s+(?:OR\s+\w+\s+)?INTO\s+[`"]?(\w+)[`"]?/i', $stmt, $tableMatch)) {
                            $tableName = $tableMatch[1];
                            if (!$this->tableExists($dstPdo, $tableName)) {
                                // Table doesn't exist - create it with a basic schema
                                try {
                                    $this->createBasicTableSchema($dstPdo, $stmt, $tableName);
                                    $created++;
                                    
                                    // Extract actual columns being inserted
                                    $colsInfo = "unknown columns";
                                    if (preg_match('/^INSERT\s+(?:OR\s+\w+\s+)?INTO\s+[`"]?\w+[`"]?\s*\(([^)]+)\)/i', $stmt, $colsMatch)) {
                                        $cols = array_map('trim', explode(',', $colsMatch[1]));
                                        $cols = array_map(function($c) { return trim($c, '`"'); }, $cols);
                                        $colsInfo = implode(', ', array_slice($cols, 0, 6)) . (count($cols) > 6 ? ', ...' : '');
                                    }
                                    
                                    $autoCreatedTables[$tableName] = $colsInfo;
                                    $log[] = "🔧 Auto-created table '{$tableName}' with columns: {$colsInfo}";
                                } catch (\Exception $e) {
                                    $errors[] = "Could not auto-create table '{$tableName}': " . substr($e->getMessage(), 0, 100);
                                    continue;
                                }
                            }
                        }

                        $result = $this->executeInsertWithPreparedStmt(
                            $dstPdo,
                            $stmt,
                            $isMysqlDump && $this->isSqlite(),
                            !$isMysqlDump && $this->isMysql()
                        );

                        if ($result['success']) {
                            $inserted++;
                        } else {
                            $errors[] = substr($result['error'], 0, 120) . ' | SQL: ' . substr($stmt, 0, 150);
                        }
                    } else {
                        // Track other statements (SET, USE, etc.) that are being skipped
                        $skipReason = '';
                        if (preg_match('/^SET\s+/i', $stmt)) {
                            $skipReason = 'SET statement (MySQL-specific)';
                            $skipped++;
                            $skippedStatements[] = "⏭ {$skipReason}";
                        } elseif (preg_match('/^(LOCK|UNLOCK|START|USE|PRAGMA)\s+/i', $stmt)) {
                            $skipReason = 'Database control statement (filtered)';
                            $skipped++;
                            $skippedStatements[] = "⏭ {$skipReason}";
                        } elseif (preg_match('/^(--|\/\*)/i', $stmt)) {
                            $skipped++;
                            // Don't log comments
                        } else {
                            // Try to convert
                            if ($isMysqlDump && $this->isSqlite()) {
                                $stmt = $this->mysqlToSqlite($stmt);
                            } elseif (!$isMysqlDump && $this->isMysql()) {
                                $stmt = $this->sqliteToMysql($stmt);
                            }

                            if (empty($stmt)) {
                                $skipped++;
                                $skippedStatements[] = "⏭ Statement filtered after conversion";
                                continue;
                            }

                            try {
                                $dstPdo->exec($stmt);
                            } catch (\Exception $e) {
                                $errors[] = substr($e->getMessage(), 0, 120) . ' | SQL: ' . substr($stmt, 0, 150);
                            }
                        }
                    }
                }

                if ($this->isSqlite()) {
                    $dstPdo->exec('PRAGMA foreign_keys = ON');
                } else {
                    $dstPdo->exec('SET FOREIGN_KEY_CHECKS=1');
                }

                $log[] = "";
                $log[] = "📊 Summary:";
                $log[] = "  ✓ {$created} tables created";
                $log[] = "  ✓ {$inserted} rows inserted";
                $log[] = "  ⚠️ " . count($errors) . " errors";
                $log[] = "  ⏭ {$skipped} statements skipped";

                // Show auto-created tables, if any
                if (!empty($autoCreatedTables)) {
                    $log[] = "";
                    $log[] = "🔧 Auto-created Tables (missing from dump):";
                    foreach ($autoCreatedTables as $table => $columns) {
                        $log[] = "   • {$table} with columns: {$columns}";
                    }
                    $log[] = "   ⚠️ Note: These tables have a BASIC schema. Review and adjust if needed.";
                }

                // Show sample of skipped statements
                if (!empty($skippedStatements)) {
                    $log[] = "";
                    $log[] = "⏭ Skipped Statements (" . count($skippedStatements) . " total):";
                    foreach (array_slice($skippedStatements, 0, 15) as $skipped) {
                        $log[] = "   {$skipped}";
                    }
                    if (count($skippedStatements) > 15) {
                        $log[] = "   ... and " . (count($skippedStatements) - 15) . " more skipped statements";
                    }
                }

                if ($errors) {
                    $log[] = "";
                    $log[] = "❌ Errors Encountered:";
                    foreach (array_slice($errors, 0, 10) as $err) {
                        $log[] = "   {$err}";
                    }
                    if (count($errors) > 10) {
                        $log[] = "   ... and " . (count($errors) - 10) . " more errors";
                    }
                }
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }

        return redirect('/dbmanager/import')->with('import_log', $log)->with('success', 'Import completed');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Check if a table exists in the current database
     */
    private function tableExists(\PDO $pdo, string $table): bool
    {
        try {
            if ($this->isSqlite()) {
                return (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
            } else {
                $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a basic table schema from INSERT statement columns
     * Used when table doesn't exist but INSERT statement does
     */
    private function createBasicTableSchema(\PDO $pdo, string $insertStmt, string $tableName): void
    {
        // Extract columns from INSERT statement
        if (!preg_match('/^INSERT\s+(?:OR\s+\w+\s+)?INTO\s+[`"]?\w+[`"]?\s*\(([^)]+)\)/i', $insertStmt, $m)) {
            throw new \Exception("Could not parse columns from INSERT statement");
        }

        $colsStr = $m[1];
        $cols = array_map('trim', explode(',', $colsStr));
        $cols = array_map(function($c) { return trim($c, '`"'); }, $cols);

        // Build CREATE TABLE with basic TEXT type and id as primary key
        $defs = [];
        $hasPk = false;

        foreach ($cols as $col) {
            if ($col === 'id') {
                if ($this->isSqlite()) {
                    $defs[] = '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
                } else {
                    $defs[] = '`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
                }
                $hasPk = true;
            } elseif (!$hasPk && $col === '{$tableName}_id') {
                if ($this->isSqlite()) {
                    $defs[] = "\"{$col}\" INTEGER PRIMARY KEY AUTOINCREMENT";
                } else {
                    $defs[] = "`{$col}` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
                }
                $hasPk = true;
            } else {
                if ($this->isSqlite()) {
                    $defs[] = "\"{$col}\" TEXT";
                } else {
                    $defs[] = "`{$col}` LONGTEXT";
                }
            }
        }

        // Add timestamps if not present
        if (!in_array('created_at', $cols)) {
            if ($this->isSqlite()) {
                $defs[] = '"created_at" TEXT DEFAULT NULL';
            } else {
                $defs[] = '`created_at` TIMESTAMP DEFAULT NULL';
            }
        }
        if (!in_array('updated_at', $cols)) {
            if ($this->isSqlite()) {
                $defs[] = '"updated_at" TEXT DEFAULT NULL';
            } else {
                $defs[] = '`updated_at` TIMESTAMP DEFAULT NULL';
            }
        }

        $sql = $this->isSqlite()
            ? 'CREATE TABLE "' . $tableName . '" (' . implode(', ', $defs) . ')'
            : 'CREATE TABLE `' . $tableName . '` (' . implode(', ', $defs) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

        $pdo->exec($sql);
    }

    /**
     * Execute INSERT statement using prepared statements to handle complex data properly
     * This avoids quote/escape issues when importing serialized data
     */
    private function executeInsertWithPreparedStmt(\PDO $pdo, string $stmt, bool $fromMysql, bool $toMysql): array
    {
        try {
            // Parse INSERT statement: extract table name, columns, and values
            // Handle both: INSERT INTO `table` (...) VALUES (...), (...)
            //        and: INSERT OR REPLACE INTO "table" (...) VALUES (...)
            if (!preg_match('/^INSERT\s+(?:OR\s+\w+\s+)?INTO\s+([`"]?)(\w+)\1\s*\(([^)]+)\)\s+VALUES\s+(.*)/is', $stmt, $m)) {
                return ['success' => false, 'error' => 'Could not parse INSERT statement'];
            }

            $quote = $m[1]; // ` or "
            $table = $m[2];
            $colsStr = $m[3];
            $valuesStr = $m[4];

            // Parse column names
            $cols = array_map('trim', explode(',', $colsStr));
            $cols = array_map(function($c) { return trim($c, '`"'); }, $cols);

            // Extract individual value tuples (handle multiple rows)
            $valueTuples = $this->parseInsertValueTuples($valuesStr);
            if (empty($valueTuples)) {
                return ['success' => false, 'error' => 'No values found in INSERT statement'];
            }

            // Build prepared statement (for first tuple, we can reuse for multiple rows)
            $colList = implode(', ', array_map(function($c) use ($pdo) {
                return $this->isSqlite() ? "\"$c\"" : "`$c`";
            }, $cols));

            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $sqlTemplate = "INSERT OR REPLACE INTO \"$table\" ($colList) VALUES ($placeholders)";
            if ($this->isMysql()) {
                $sqlTemplate = "REPLACE INTO `$table` ($colList) VALUES ($placeholders)";
            }

            $pStmt = $pdo->prepare($sqlTemplate);

            // Execute each value tuple with proper parameter binding
            foreach ($valueTuples as $values) {
                if (count($values) !== count($cols)) {
                    // Skip malformed rows
                    continue;
                }

                // Convert values from source format to target format
                $convertedValues = [];
                foreach ($values as $i => $val) {
                    if ($val === null) {
                        $convertedValues[$i] = null;
                    } else {
                        // Convert escape sequences from source to target
                        if ($fromMysql) {
                            // MySQL: unescape backslash escapes (\', \", \\, etc.)
                            $val = $this->unescapeMysqlString($val);
                        } elseif ($toMysql) {
                            // SQLite to MySQL: may need re-escaping
                            // (PDO will handle it via parameterized queries)
                        }
                        $convertedValues[$i] = $val;
                    }
                }

                try {
                    $pStmt->execute($convertedValues);
                } catch (\Exception $e) {
                    // Continue with next row even if this one fails
                    continue;
                }
            }

            return ['success' => true, 'error' => null];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse INSERT VALUES clause to extract individual value tuples
     * Handles: (val1, val2), (val3, val4), etc.
     * Properly handles quoted strings and escaped quotes
     */
    private function parseInsertValueTuples(string $valuesStr): array
    {
        $tuples = [];
        $i = 0;
        $len = strlen($valuesStr);
        
        while ($i < $len) {
            // Skip whitespace
            while ($i < $len && ctype_space($valuesStr[$i])) $i++;
            
            if ($i >= $len || $valuesStr[$i] !== '(') {
                break;
            }

            $i++; // Skip opening (
            $tuple = [];
            $inQuote = false;
            $quoteChar = '';
            $value = '';

            while ($i < $len) {
                $ch = $valuesStr[$i];

                if (!$inQuote && ($ch === "'" || $ch === '"')) {
                    $inQuote = true;
                    $quoteChar = $ch;
                    $value .= $ch;
                } elseif ($inQuote && $ch === $quoteChar) {
                    // Check for escaped quote
                    if ($i + 1 < $len && $valuesStr[$i + 1] === $quoteChar) {
                        // Doubled quote - add both and skip next
                        $value .= $ch . $ch;
                        $i++;
                    } else {
                        // End of quoted string
                        $value .= $ch;
                        $inQuote = false;
                    }
                } elseif (!$inQuote && $ch === ',') {
                    // End of value
                    $tuple[] = trim($value);
                    $value = '';
                } elseif (!$inQuote && $ch === ')') {
                    // End of tuple
                    if ($value !== '' || count($tuple) > 0) {
                        $tuple[] = trim($value);
                    }
                    $tuples[] = $tuple;
                    $i++; // Skip closing )
                    break;
                } else {
                    $value .= $ch;
                }

                $i++;
            }

            // Skip comma and whitespace between tuples
            while ($i < $len && (ctype_space($valuesStr[$i]) || $valuesStr[$i] === ',')) $i++;
        }

        // Convert each value string to actual PHP value (NULL, numeric, or string)
        return array_map([$this, 'convertInsertValue'], $tuples);
    }

    /**
     * Convert a tuple of value strings to PHP values
     * Handles NULL, numbers, and quoted strings
     */
    private function convertInsertValue(array $valueStrings): array
    {
        return array_map(function($v) {
            $v = trim($v);
            
            // NULL
            if (strtoupper($v) === 'NULL') {
                return null;
            }

            // Remove quotes and unescape
            if (($v[0] === "'" && $v[strlen($v)-1] === "'") || 
                ($v[0] === '"' && $v[strlen($v)-1] === '"')) {
                // Remove surrounding quotes
                $v = substr($v, 1, -1);
                // Unescape doubled quotes
                $v = str_replace("''", "'", $v);
                $v = str_replace('""', '"', $v);
                return $v;
            }

            // Bare numbers or other values
            return $v;
        }, $valueStrings);
    }

    /**
     * Unescape MySQL string escape sequences
     */
    private function unescapeMysqlString(string $str): string
    {
        // Handle MySQL escape sequences: \', \", \\, \n, \r, \t, \0, etc.
        $str = str_replace("\\'", "'", $str);
        $str = str_replace('\\"', '"', $str);
        $str = str_replace('\\\\', '\\', $str);
        $str = str_replace('\\n', "\n", $str);
        $str = str_replace('\\r', "\r", $str);
        $str = str_replace('\\t', "\t", $str);
        $str = str_replace('\\0', "\0", $str);
        return $str;
    }

    private function ensureTableExists(\PDO $pdo, string $table, array $srcCols): void
    {
        // Check if table exists
        if ($this->isSqlite()) {
            $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
        } else {
            try {
                $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
                $exists = true;
            } catch (\Exception $e) {
                $exists = false;
            }
        }

        if ($exists) return;

        // Build CREATE TABLE from source columns
        $defs = [];
        foreach ($srcCols as $col) {
            $type = $col['type'] ?: 'TEXT';
            if ($this->isMysql()) {
                $type = $this->mapSqliteTypeToMysql($type);
            }
            $def = "`{$col['name']}` {$type}";
            if ($col['pk']) {
                $def .= $this->isSqlite() ? ' PRIMARY KEY AUTOINCREMENT' : ' PRIMARY KEY AUTO_INCREMENT';
            } elseif ($col['notnull']) {
                $def .= ' NOT NULL';
            }
            if ($col['dflt_value'] !== null && !$col['pk']) {
                $def .= " DEFAULT '{$col['dflt_value']}'";
            }
            $defs[] = $def;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (".implode(', ', $defs).")");
    }

    private function getDestColNames(\PDO $pdo, string $table): array
    {
        if ($this->isSqlite()) {
            return array_column(
                $pdo->query("PRAGMA table_info(\"{$table}\")")->fetchAll(\PDO::FETCH_ASSOC),
                'name'
            );
        } else {
            return array_column(
                $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC),
                'Field'
            );
        }
    }

    private function mapSqliteTypeToMysql(string $type): string
    {
        $map = [
            'INTEGER'  => 'BIGINT',
            'REAL'     => 'DOUBLE',
            'NUMERIC'  => 'DECIMAL(15,4)',
            'BLOB'     => 'LONGBLOB',
            'TEXT'     => 'LONGTEXT',
            'BOOLEAN'  => 'TINYINT(1)',
            'DATE'     => 'DATE',
            'DATETIME' => 'DATETIME',
            'TIMESTAMP'=> 'TIMESTAMP',
        ];
        $upper = strtoupper($type);
        return $map[$upper] ?? $type;
    }

    private function parseSqlStatements(string $sql): array
    {
        // Remove block comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        $statements = [];
        $current    = '';
        $inString   = false;
        $strChar    = '';
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            // Skip line comments outside strings
            if (!$inString && $ch === '-' && isset($sql[$i+1]) && $sql[$i+1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }
            if (!$inString && $ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

            // Track string boundaries
            if (!$inString && ($ch === "'" || $ch === '"')) {
                $inString = true;
                $strChar  = $ch;
            } elseif ($inString && $ch === $strChar) {
                // Handle escaped quote ('')
                if (isset($sql[$i+1]) && $sql[$i+1] === $strChar) {
                    $current .= $ch;
                    $i++;
                } else {
                    $inString = false;
                }
            }

            if (!$inString && $ch === ';') {
                $stmt = trim($current);
                if ($stmt !== '') $statements[] = $stmt;
                $current = '';
            } else {
                $current .= $ch;
            }
        }

        $stmt = trim($current);
        if ($stmt !== '') $statements[] = $stmt;

        return $statements;
    }

    private function mysqlToSqlite(string $sql): string
    {
        $trimmed = ltrim($sql);

        // ── Skip all MySQL-only statements ────────────────────────────────────
        if (preg_match('/^(SET\s|LOCK\s|UNLOCK\s|START\s+TRANSACTION|COMMIT|ROLLBACK|USE\s)/i', $trimmed)) {
            return '';
        }
        if (substr($trimmed, 0, 3) === '/*!') {
            return '';
        }
        if (preg_match('/^ALTER\s+TABLE/i', $trimmed)) {
            return '';
        }
        if (preg_match('/^(CREATE\s+(UNIQUE\s+)?INDEX|DROP\s+INDEX)/i', $trimmed)) {
            return '';
        }

        // ── CREATE TABLE ──────────────────────────────────────────────────────
        if (preg_match('/^CREATE\s+TABLE/i', $trimmed)) {

            // 1. Strip everything after the closing paren (ENGINE=, CHARSET=, etc.)
            //    Find the last ) and cut everything after it
            $lastParen = strrpos($sql, ')');
            if ($lastParen !== false) {
                $sql = substr($sql, 0, $lastParen + 1);
            }

            // 2. Remove KEY/INDEX/CONSTRAINT lines inside CREATE TABLE body
            $sql = preg_replace('/,\s*(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?\w*`?\s*\([^)]+\)/i', '', $sql);
            $sql = preg_replace('/,\s*PRIMARY\s+KEY\s*\([^)]+\)/i', '', $sql);
            $sql = preg_replace('/,\s*CONSTRAINT\s+`?\w+`?\s+FOREIGN\s+KEY\b[^,)]*/i', '', $sql);
            $sql = preg_replace('/,\s*REFERENCES\s+`?\w+`?\s*\([^)]+\)[^,)]*/i', '', $sql);

            // 3. Convert AUTO_INCREMENT columns → INTEGER PRIMARY KEY AUTOINCREMENT
            //    Must run BEFORE generic INT conversion
            $sql = preg_replace(
                '/`(\w+)`\s+(?:BIGINT|INT|SMALLINT|TINYINT|MEDIUMINT)(?:\s+UNSIGNED)?(?:\s*\(\d+\))?\s+(?:NOT\s+NULL\s+)?AUTO_INCREMENT/i',
                '"$1" INTEGER PRIMARY KEY AUTOINCREMENT',
                $sql
            );

            // 4. Remove column-level attributes before type conversion
            $sql = preg_replace('/\bCHARACTER\s+SET\s+\w+/i', '', $sql);
            $sql = preg_replace('/\bCOLLATE\s+\w+/i', '', $sql);
            $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);
            $sql = preg_replace('/\bZEROFILL\b/i', '', $sql);
            $sql = preg_replace('/\bON\s+UPDATE\s+\w+\s*\(\s*\)/i', '', $sql);

            // 5. Fix DEFAULT current_timestamp() before type conversion touches TIMESTAMP
            $sql = preg_replace('/DEFAULT\s+current_timestamp\s*\(\s*\)/i', 'DEFAULT CURRENT_TIMESTAMP', $sql);

            // 6. Type mappings — CRITICAL: use negative lookahead on INT to avoid
            //    matching inside INTEGER, TINYINT, BIGINT etc.
            //    Order: most specific first
            $sql = preg_replace('/\bTINYINT\s*\(\s*1\s*\)/i',                    'INTEGER', $sql);
            $sql = preg_replace('/\bTINYINT(?:\s*\(\d+\))?/i',                   'INTEGER', $sql);
            $sql = preg_replace('/\bSMALLINT(?:\s*\(\d+\))?/i',                  'INTEGER', $sql);
            $sql = preg_replace('/\bMEDIUMINT(?:\s*\(\d+\))?/i',                 'INTEGER', $sql);
            $sql = preg_replace('/\bBIGINT(?:\s*\(\d+\))?/i',                    'INTEGER', $sql);
            // INT only — negative lookahead to not match INTEGER, TINYINT etc.
            $sql = preg_replace('/\bINT(?!EGER)(?:\s*\(\d+\))?/i',               'INTEGER', $sql);
            $sql = preg_replace('/\bDOUBLE(?:\s*\(\d+,\d+\))?/i',                'REAL',    $sql);
            $sql = preg_replace('/\bFLOAT(?:\s*\(\d+,\d+\))?/i',                 'REAL',    $sql);
            $sql = preg_replace('/\bDECIMAL(?:\s*\(\d+,\d+\))?/i',               'NUMERIC', $sql);
            $sql = preg_replace('/\bVARCHAR\s*\(\d+\)/i',                         'TEXT',    $sql);
            $sql = preg_replace('/\bCHAR\s*\(\d+\)/i',                            'TEXT',    $sql);
            $sql = preg_replace('/\b(?:TINY|MEDIUM|LONG)?TEXT\b/i',               'TEXT',    $sql);
            $sql = preg_replace('/\b(?:TINY|MEDIUM|LONG)?BLOB\b/i',               'BLOB',    $sql);
            $sql = preg_replace('/\bDATETIME\b/i',                                'TEXT',    $sql);
            $sql = preg_replace('/\bTIMESTAMP\b/i',                               'TEXT',    $sql);
            $sql = preg_replace('/\bDATE\b/i',                                    'TEXT',    $sql);
            $sql = preg_replace('/\bTIME\b/i',                                    'TEXT',    $sql);
            $sql = preg_replace('/\bYEAR\b/i',                                    'INTEGER', $sql);
            $sql = preg_replace('/\bJSON\b/i',                                    'TEXT',    $sql);
            $sql = preg_replace('/\bENUM\s*\([^)]+\)/i',                          'TEXT',    $sql);
            $sql = preg_replace('/\bSET\s*\([^)]+\)/i',                           'TEXT',    $sql);

            // 7. Replace backticks with double-quotes
            $sql = str_replace('`', '"', $sql);

            // 8. Clean up trailing commas before closing paren
            $sql = preg_replace('/,\s*\)/', "\n)", $sql);

            // 9. Always drop + recreate to ensure schema matches the dump exactly
            $sql = preg_replace('/CREATE\s+TABLE\s+"/i', 'CREATE TABLE "', $sql);
            // Store the table name so the caller can drop it first
            if (preg_match('/CREATE\s+TABLE\s+"(\w+)"/i', $sql, $m)) {
                $sql = '__DROP__' . $m[1] . '__' . $sql;
            }

            return trim($sql);
        }

        // ── INSERT ────────────────────────────────────────────────────────────
        if (preg_match('/^INSERT\s+(?:INTO\s+)?`/i', $trimmed)) {
            return str_replace('`', '"', $sql);
        }

        return trim($sql);
    }

    private function sqliteToMysql(string $sql): string
    {
        // Skip SQLite-specific
        if (preg_match('/^(PRAGMA|BEGIN TRANSACTION|COMMIT|ROLLBACK)/i', $sql)) {
            return '';
        }

        if (stripos($sql, 'CREATE TABLE') === 0) {
            // Convert AUTOINCREMENT
            $sql = preg_replace('/INTEGER\s+PRIMARY KEY\s+AUTOINCREMENT/i', 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $sql);
            $sql = preg_replace('/INTEGER\s+PRIMARY KEY/i', 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $sql);
            // Type mappings
            $sql = preg_replace('/\bINTEGER\b/i', 'BIGINT', $sql);
            $sql = preg_replace('/\bREAL\b/i', 'DOUBLE', $sql);
            $sql = preg_replace('/\bNUMERIC\b/i', 'DECIMAL(15,4)', $sql);
            $sql = preg_replace('/\bBLOB\b/i', 'LONGBLOB', $sql);
            $sql = preg_replace('/\bTEXT\b/i', 'LONGTEXT', $sql);
            // Replace double-quotes with backticks
            $sql = str_replace('"', '`', $sql);
            // Add IF NOT EXISTS
            $sql = preg_replace('/CREATE TABLE\s+`/i', 'CREATE TABLE IF NOT EXISTS `', $sql);
            // Add ENGINE
            $sql = rtrim($sql, ')') . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        }

        if (stripos($sql, 'INSERT') === 0) {
            $sql = str_replace('"', '`', $sql);
        }

        return trim($sql);
    }
}
