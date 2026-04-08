<?php

namespace Devtoolkit\DbManager;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DbManagerController extends Controller
{
    private function pdo(): \PDO
    {
        return DB::connection()->getPdo();
    }

    private function driver(): string
    {
        return config('database.default');
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

        $path     = $file->getRealPath();
        $ext      = strtolower($file->getClientOriginalExtension());
        $content  = file_get_contents($path);
        $mode     = $request->input('mode', 'auto'); // auto | replace | merge

        // ── Detect source format ──────────────────────────────────────────────
        $isSqliteFile = (substr($content, 0, 15) === 'SQLite format 3');
        $isSqlDump    = !$isSqliteFile && (
            stripos($content, 'CREATE TABLE') !== false ||
            stripos($content, 'INSERT INTO')  !== false
        );

        if (!$isSqliteFile && !$isSqlDump) {
            return back()->with('error', 'Unrecognized file format. Upload a .sqlite file or a .sql dump.');
        }

        $log = [];

        try {
            if ($isSqliteFile) {
                // ── Source: SQLite file → import into current DB ──────────────
                $srcPdo = new \PDO("sqlite:{$path}");
                $srcPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                $srcTables = $srcPdo->query(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                )->fetchAll(\PDO::FETCH_COLUMN);

                $dstPdo = $this->pdo();

                foreach ($srcTables as $table) {
                    $rows = $srcPdo->query("SELECT * FROM \"{$table}\"")->fetchAll(\PDO::FETCH_ASSOC);
                    if (empty($rows)) { $log[] = "⏭ {$table}: empty, skipped"; continue; }

                    // Get source columns
                    $srcCols = $srcPdo->query("PRAGMA table_info(\"{$table}\")")->fetchAll(\PDO::FETCH_ASSOC);

                    // Ensure table exists in destination
                    $this->ensureTableExists($dstPdo, $table, $srcCols);

                    // Get destination columns to only insert matching ones
                    $dstColNames = $this->getDestColNames($dstPdo, $table);

                    $inserted = 0; $skipped = 0;
                    foreach ($rows as $row) {
                        $ins = []; $vals = [];
                        foreach ($row as $col => $val) {
                            if (in_array($col, $dstColNames)) {
                                $ins[]  = "`{$col}`";
                                $vals[] = $val;
                            }
                        }
                        if (!$ins) continue;
                        $ph = implode(',', array_fill(0, count($ins), '?'));
                        $sql = $mode === 'replace'
                            ? "REPLACE INTO `{$table}` (".implode(',', $ins).") VALUES ({$ph})"
                            : "INSERT IGNORE INTO `{$table}` (".implode(',', $ins).") VALUES ({$ph})";

                        // SQLite doesn't support INSERT IGNORE / REPLACE the same way
                        if ($this->isSqlite()) {
                            $sql = $mode === 'replace'
                                ? "INSERT OR REPLACE INTO `{$table}` (".implode(',', $ins).") VALUES ({$ph})"
                                : "INSERT OR IGNORE INTO `{$table}` (".implode(',', $ins).") VALUES ({$ph})";
                        }

                        try {
                            $dstPdo->prepare($sql)->execute($vals);
                            $inserted++;
                        } catch (\Exception $e) {
                            $skipped++;
                        }
                    }
                    $log[] = "✅ {$table}: {$inserted} inserted, {$skipped} skipped";
                }

            } else {
                // ── Source: SQL dump → parse & import into current DB ─────────
                // Detect if dump is MySQL or SQLite syntax
                $isMysqlDump = stripos($content, 'ENGINE=') !== false
                    || stripos($content, 'AUTO_INCREMENT') !== false
                    || stripos($content, '/*!') !== false;

                $statements = $this->parseSqlStatements($content);
                $dstPdo     = $this->pdo();
                $inserted   = 0; $created = 0; $errors = 0;

                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (empty($stmt) || substr($stmt, 0, 2) === '--') continue;

                    // Convert syntax if needed
                    if ($isMysqlDump && $this->isSqlite()) {
                        $stmt = $this->mysqlToSqlite($stmt);
                    } elseif (!$isMysqlDump && $this->isMysql()) {
                        $stmt = $this->sqliteToMysql($stmt);
                    }

                    if (empty($stmt)) continue;

                    try {
                        $dstPdo->exec($stmt);
                        if (stripos($stmt, 'CREATE TABLE') === 0) $created++;
                        if (stripos($stmt, 'INSERT') === 0) $inserted++;
                    } catch (\Exception $e) {
                        $errors++;
                        // Don't abort on individual statement errors
                    }
                }
                $log[] = "✅ SQL dump imported: {$created} tables created, {$inserted} rows inserted, {$errors} errors";
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }

        return redirect('/dbmanager/import')->with('import_log', $log)->with('success', 'Import completed successfully');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
        // Remove comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/^#.*$/m', '', $sql);

        // Split on semicolons (naive but works for standard dumps)
        $parts = explode(';', $sql);
        return array_filter(array_map('trim', $parts));
    }

    private function mysqlToSqlite(string $sql): string
    {
        // Skip MySQL-specific statements
        if (preg_match('/^(SET|LOCK|UNLOCK|\/\*!|USE\s|ALTER\s+TABLE.*ADD\s+(KEY|INDEX|UNIQUE|CONSTRAINT|FOREIGN))/i', $sql)) {
            return '';
        }

        // CREATE TABLE conversions
        if (stripos($sql, 'CREATE TABLE') === 0) {
            // Remove ENGINE, CHARSET, COLLATE, AUTO_INCREMENT table options
            $sql = preg_replace('/\s*(ENGINE|DEFAULT CHARSET|COLLATE|AUTO_INCREMENT|ROW_FORMAT|COMMENT)\s*=\s*\S+/i', '', $sql);
            // Remove KEY/INDEX lines inside CREATE TABLE
            $sql = preg_replace('/,\s*(PRIMARY\s+)?KEY\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
            $sql = preg_replace('/,\s*(UNIQUE\s+)?INDEX\s+`?\w+`?\s*\([^)]+\)/i', '', $sql);
            $sql = preg_replace('/,\s*CONSTRAINT\s+`?\w+`?\s+FOREIGN KEY[^,)]+/i', '', $sql);
            // Convert AUTO_INCREMENT column definition
            $sql = preg_replace('/\bINT\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = preg_replace('/\bBIGINT\s+UNSIGNED\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = preg_replace('/\bBIGINT\s+NOT\s+NULL\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = preg_replace('/\bINT\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = preg_replace('/\bBIGINT\s+UNSIGNED\s+AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            // Remove PRIMARY KEY constraint if already inline
            $sql = preg_replace('/,\s*PRIMARY KEY\s*\(`?\w+`?\)/i', '', $sql);
            // Type mappings
            $sql = preg_replace('/\bTINYINT\(\d+\)/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bSMALLINT\(\d+\)/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bMEDIUMINT\(\d+\)/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bBIGINT\s+UNSIGNED/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bBIGINT(\(\d+\))?/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bINT(\(\d+\))?\s+UNSIGNED/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bINT(\(\d+\))?/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bDOUBLE(\(\d+,\d+\))?/i', 'REAL', $sql);
            $sql = preg_replace('/\bFLOAT(\(\d+,\d+\))?/i', 'REAL', $sql);
            $sql = preg_replace('/\bDECIMAL(\(\d+,\d+\))?/i', 'NUMERIC', $sql);
            $sql = preg_replace('/\bVARCHAR\(\d+\)/i', 'TEXT', $sql);
            $sql = preg_replace('/\bCHAR\(\d+\)/i', 'TEXT', $sql);
            $sql = preg_replace('/\b(MEDIUM|LONG)?TEXT/i', 'TEXT', $sql);
            $sql = preg_replace('/\b(MEDIUM|LONG)?BLOB/i', 'BLOB', $sql);
            $sql = preg_replace('/\bTINYINT\(1\)/i', 'INTEGER', $sql);
            // Remove backticks
            $sql = str_replace('`', '"', $sql);
            // Remove trailing commas before closing paren
            $sql = preg_replace('/,\s*\)/', ')', $sql);
            // Add IF NOT EXISTS
            $sql = preg_replace('/CREATE TABLE\s+"/i', 'CREATE TABLE IF NOT EXISTS "', $sql);
        }

        // INSERT: replace backticks
        if (stripos($sql, 'INSERT') === 0) {
            $sql = str_replace('`', '"', $sql);
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
