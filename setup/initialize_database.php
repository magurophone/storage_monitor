<?php
/**
 * 最適化されたデータベース初期化スクリプト
 * storage_reportsテーブルを削除し、構造をシンプル化
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';

// ログにメッセージを記録 - 必要最小限のみ
function logMessage($message, $level = 'INFO') {
    // サーバー負荷を減らすため、重要なメッセージのみをログに記録
    if ($level === 'ERROR' || $level === 'WARNING') {
        $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs';
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/setup_' . date('Y-m-d') . '.log';
        $formattedMessage = date('Y-m-d H:i:s') . " [{$level}] - " . $message . PHP_EOL;
        
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }
    
    // コンソールにも出力
    echo "$level: $message\n";
}

try {
    echo "データベース初期化開始\n";
    
    // データベース接続
    $db = Database::getInstance();
    echo "データベース接続完了\n";
    
    // トランザクション開始
    $db->beginTransaction();
    echo "トランザクション開始完了\n";
    
    // 外部キー制約チェックを無効化
    echo "外部キー制約チェックを無効化\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // 既存のテーブルが存在するか確認
    $tables = $db->fetchAll("SHOW TABLES");
    $existingTables = [];
    
    foreach ($tables as $tableRow) {
        $tableName = reset($tableRow);
        $existingTables[] = $tableName;
    }
    
    echo "既存のテーブル: " . implode(", ", $existingTables) . "\n";
    
    // sm_storage_reportsテーブルが存在する場合は削除
    if (in_array('sm_storage_reports', $existingTables)) {
        $db->exec("DROP TABLE IF EXISTS sm_storage_reports");
        echo "sm_storage_reports テーブル削除完了\n";
    } else {
        echo "sm_storage_reports テーブルは存在しません\n";
    }
    
    // 既存のテーブルを確認して削除（存在する場合のみ）
    if (in_array('sm_storage_data', $existingTables)) {
        $db->exec("DROP TABLE IF EXISTS sm_storage_data");
        echo "sm_storage_data テーブル削除完了\n";
    } else {
        echo "sm_storage_data テーブルは存在しません\n";
    }
    
    if (in_array('sm_devices', $existingTables)) {
        $db->exec("DROP TABLE IF EXISTS sm_devices");
        echo "sm_devices テーブル削除完了\n";
    } else {
        echo "sm_devices テーブルは存在しません\n";
    }
    
    // 外部キー制約チェックを再有効化
    echo "外部キー制約チェックを再有効化\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 最適化されたsm_devicesテーブルの作成
    echo "sm_devicesテーブル作成開始\n";
    
    $db->exec("
        CREATE TABLE sm_devices (
            device_number INT PRIMARY KEY,
            last_update DATETIME
        )
    ");
    echo "sm_devicesテーブル作成完了\n";
    
    // 最適化されたsm_storage_dataテーブルの作成
    echo "sm_storage_dataテーブル作成開始\n";
    
    $db->exec("
        CREATE TABLE sm_storage_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_number INT NOT NULL,
            free_space BIGINT,
            created_at DATETIME,
            FOREIGN KEY (device_number) REFERENCES sm_devices(device_number)
        )
    ");
    echo "sm_storage_dataテーブル作成完了\n";
    
    // インデックスの作成
    echo "インデックス作成開始\n";
    
    $db->exec("CREATE INDEX idx_created_at ON sm_storage_data (created_at)");
    echo "created_at インデックス作成完了\n";
    
    // トランザクション確定
    echo "トランザクションコミット直前\n";
    $db->commit();
    echo "トランザクションコミット完了\n";
    
    logMessage("データベース初期化が正常に完了しました", "SUCCESS");
    
    echo "スクリプト正常終了\n";
    exit(0);
} catch (Exception $e) {
    echo "例外発生: " . $e->getMessage() . "\n";
    
    // エラー発生時はロールバック
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
        echo "トランザクションロールバック完了\n";
    }
    
    logMessage("データベース初期化エラー: " . $e->getMessage(), "ERROR");
    
    echo "スクリプトエラー終了\n";
    exit(1);
}