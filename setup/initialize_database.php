<?php
/**
 * データベース初期化スクリプト - 簡略化バージョン
 */

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';

try {
    // データベース接続
    $db = Database::getInstance();
    
    // 既存のテーブルを削除（存在する場合）
    echo "テーブルの削除を開始します...\n";
    $db->exec("DROP TABLE IF EXISTS storage_data");
    $db->exec("DROP TABLE IF EXISTS storage_report_details");
    $db->exec("DROP TABLE IF EXISTS storage_reports");
    $db->exec("DROP TABLE IF EXISTS devices");
    echo "テーブルの削除が完了しました\n";
    
    // ここでトランザクションを開始（テーブル削除後）
    echo "トランザクション開始を試みます...\n";
    $db->beginTransaction();
    echo "トランザクション開始完了\n";
    
    // デバイステーブルの作成
    $db->exec("
        CREATE TABLE devices (
            device_id VARCHAR(50) PRIMARY KEY,
            device_number INT NOT NULL UNIQUE,
            last_update DATETIME
        )
    ");
    
    // トランザクションの状態をチェック
    if ($db->inTransaction()) {
        echo "devices テーブル作成後もトランザクションはアクティブです\n";
    } else {
        echo "警告: devices テーブル作成後にトランザクションが非アクティブになりました\n";
        // 再度トランザクションを開始
        $db->beginTransaction();
    }
    
    // ストレージデータテーブルの作成
    $db->exec("
        CREATE TABLE storage_data (
            device_id VARCHAR(50) PRIMARY KEY,
            free_space BIGINT,
            timestamp DATETIME,
            FOREIGN KEY (device_id) REFERENCES devices(device_id)
        )
    ");
    
    // トランザクションの状態をチェック
    if ($db->inTransaction()) {
        echo "storage_data テーブル作成後もトランザクションはアクティブです\n";
    } else {
        echo "警告: storage_data テーブル作成後にトランザクションが非アクティブになりました\n";
        // 再度トランザクションを開始
        $db->beginTransaction();
    }
    
    // レポートテーブルの作成
    $db->exec("
        CREATE TABLE storage_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            generated_at TIMESTAMP NOT NULL,
            total_devices INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // トランザクションの状態をチェック
    if ($db->inTransaction()) {
        echo "storage_reports テーブル作成後もトランザクションはアクティブです\n";
    } else {
        echo "警告: storage_reports テーブル作成後にトランザクションが非アクティブになりました\n";
        // 再度トランザクションを開始
        $db->beginTransaction();
    }
    
    // レポート詳細テーブルの作成
    $db->exec("
        CREATE TABLE storage_report_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            device_number INT NOT NULL,
            free_space BIGINT NOT NULL,
            total_space BIGINT DEFAULT NULL,
            last_update TIMESTAMP NULL DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'unknown',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (report_id) REFERENCES storage_reports(id)
        )
    ");
    
    // インデックスの作成
    $db->exec("CREATE INDEX idx_device_number ON devices (device_number)");
    
    // トランザクションの状態をチェック
    if ($db->inTransaction()) {
        // トランザクション確定
        echo "トランザクションをコミットします...\n";
        $db->commit();
        echo "トランザクションコミット完了\n";
    } else {
        echo "警告: 最終ステップでトランザクションが非アクティブのため、コミットをスキップします\n";
    }
    
    echo "Database initialized successfully.\n";
    exit(0);
} catch (Exception $e) {
    // エラー発生時はロールバック（トランザクションがアクティブな場合のみ）
    echo 'エラー発生: ' . $e->getMessage() . "\n";
    echo 'エラータイプ: ' . get_class($e) . "\n";
    echo 'スタックトレース: ' . $e->getTraceAsString() . "\n";
    
    try {
        if (isset($db) && $db->inTransaction()) {
            echo "ロールバックを試みます...\n";
            $db->rollback();
            echo "ロールバック完了\n";
        } else {
            echo "アクティブなトランザクションがないためロールバックをスキップします\n";
        }
    } catch (PDOException $rollbackException) {
        echo "ロールバック中のエラー: " . $rollbackException->getMessage() . "\n";
    }
    
    echo 'Error initializing database: ' . $e->getMessage() . "\n";
    exit(1);
}