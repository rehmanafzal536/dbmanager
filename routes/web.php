<?php

use Illuminate\Support\Facades\Route;
use Devtoolkit\DbManager\DbManagerController;
use Devtoolkit\DbManager\DbManagerAuthController;

// Auth routes
Route::prefix('dbmanager')->name('dbmanager.')->middleware('web')->group(function () {
    Route::get('/login',  [DbManagerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [DbManagerAuthController::class, 'login'])->name('login.post');
    Route::get('/logout', [DbManagerAuthController::class, 'logout'])->name('logout');
});

// Protected routes
Route::prefix('dbmanager')->name('dbmanager.')->middleware(['web', 'dbmanager.auth'])->group(function () {

    Route::get('/settings',         [DbManagerAuthController::class, 'showSettings'])->name('settings');
    Route::post('/settings/update', [DbManagerAuthController::class, 'updateSettings'])->name('settings.update');

    Route::get('/',                [DbManagerController::class, 'index'])->name('index');
    Route::get('/sql',             [DbManagerController::class, 'runSql'])->name('sql');
    Route::post('/sql',            [DbManagerController::class, 'runSql']);
    Route::get('/create-table',    [DbManagerController::class, 'createTable'])->name('create_table');
    Route::post('/create-table',   [DbManagerController::class, 'storeTable'])->name('store_table');
    Route::delete('/drop-table/{table}', [DbManagerController::class, 'dropTable'])->name('drop_table');

    Route::get('/table/{table}',                       [DbManagerController::class, 'table'])->name('table');
    Route::get('/table/{table}/structure',             [DbManagerController::class, 'structure'])->name('structure');
    Route::post('/table/{table}/add-column',           [DbManagerController::class, 'addColumn'])->name('add_column');
    Route::post('/table/{table}/rename-column',        [DbManagerController::class, 'renameColumn'])->name('rename_column');
    Route::post('/table/{table}/modify-column',        [DbManagerController::class, 'modifyColumn'])->name('modify_column');
    Route::delete('/table/{table}/drop-column',        [DbManagerController::class, 'dropColumn'])->name('drop_column');
    Route::delete('/table/{table}/bulk-drop-columns',  [DbManagerController::class, 'bulkDropColumns'])->name('bulk_drop_columns');
    Route::post('/table/{table}/add-index',            [DbManagerController::class, 'addIndex'])->name('add_index');
    Route::delete('/table/{table}/drop-index',         [DbManagerController::class, 'dropIndex'])->name('drop_index');
    Route::post('/table/{table}/add-foreign-key',      [DbManagerController::class, 'addForeignKey'])->name('add_foreign_key');
    Route::delete('/table/{table}/drop-foreign-key',   [DbManagerController::class, 'dropForeignKey'])->name('drop_foreign_key');
    Route::get('/table/{table}/create',                [DbManagerController::class, 'createRow'])->name('create_row');
    Route::post('/table/{table}/store',                [DbManagerController::class, 'storeRow'])->name('store_row');
    Route::get('/table/{table}/edit/{id}',             [DbManagerController::class, 'editRow'])->name('edit_row');
    Route::put('/table/{table}/update/{id}',           [DbManagerController::class, 'updateRow'])->name('update_row');
    Route::delete('/table/{table}/delete/{id}',        [DbManagerController::class, 'deleteRow'])->name('delete_row');
    Route::get('/table/{table}/bulk-edit-page',        [DbManagerController::class, 'bulkEditPage'])->name('bulk_edit_page');
    Route::post('/table/{table}/bulk-update',          [DbManagerController::class, 'bulkUpdateRows'])->name('bulk_update_rows');
    Route::post('/table/{table}/inline-update',        [DbManagerController::class, 'inlineUpdate'])->name('inline_update');
    Route::delete('/table/{table}/bulk-delete',        [DbManagerController::class, 'bulkDeleteRows'])->name('bulk_delete_rows');
    Route::post('/table/{table}/bulk-edit',            [DbManagerController::class, 'bulkEditRows'])->name('bulk_edit_rows');
    Route::delete('/table/{table}/truncate',           [DbManagerController::class, 'truncateTable'])->name('truncate');
    Route::get('/table/{table}/export/csv',            [DbManagerController::class, 'exportCsv'])->name('export_csv');
    Route::get('/table/{table}/export/sql',            [DbManagerController::class, 'exportSql'])->name('export_sql');
    Route::post('/table/{table}/import/csv',           [DbManagerController::class, 'importCsv'])->name('import_csv');
    Route::get('/backup',                              [DbManagerController::class, 'backup'])->name('backup');
    Route::post('/restore',                            [DbManagerController::class, 'restore'])->name('restore');
    Route::get('/import',                              [DbManagerController::class, 'importPage'])->name('import');
    Route::post('/import/convert',                     [DbManagerController::class, 'importConvert'])->name('import_convert');
});
