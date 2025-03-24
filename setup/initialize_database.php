<?php
/**
 * データベース初期化スクリプト - 簡略化バージョン
 */

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';

try {
    // データベース接続
    $db = Database::getInstance();
    
    // トランザクション開始
    $db->beginTransaction();
    
    // 既存のテーブルを削除（存在する場合）
    $db->exec("DROP TABLE IF EXISTS storage_data");
    $db->exec("DROP TABLE IF EXISTS devices");
    
    // デバイステーブルの作成
    $db->exec("
        CREATE TABLE devices (
            device_id VARCHAR(50) PRIMARY KEY,
            device_number INT NOT NULL UNIQUE,
            last_update DATETIME
        )
    ");
    
    // ストレージデータテーブルの作成
    $db->exec("
        CREATE TABLE storage_data (
            device_id VARCHAR(50) PRIMARY KEY,
            free_space BIGINT,
            timestamp DATETIME,
            FOREIGN KEY (device_id) REFERENCES devices(device_id)
        )
    ");
    
    // インデックスの作成
    $db->exec("CREATE INDEX idx_device_number ON devices (device_number)");
    
    // トランザクション確定
    $db->commit();
    
    echo "Database initialized successfully.\n";
    exit(0);
} catch (Exception $e) {
    // エラー発生時はロールバック（トランザクションがアクティブな場合のみ）
    try {
        if (isset($db)) {
            $db->rollback();
        }
    } catch (PDOException $rollbackException) {
        // ロールバックに失敗した場合は無視
    }
    
    echo 'Error initializing database: ' . $e->getMessage() . "\n";
    exit(1);
}