<?php
/**
 * データベース初期化スクリプト - 外部キーチェック無効化版
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

echo "スクリプト開始\n";

require_once __DIR__ . '/../private/config.php';
echo "config.php 読み込み完了\n";

require_once __DIR__ . '/../private/database.php';
echo "database.php 読み込み完了\n";

// ログにメッセージを記録
function logMessage($message, $level = 'INFO') {
    // LOG_DIR定数が使用できない場合は直接ディレクトリを指定
    $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs';
    
    // ログディレクトリが存在しない場合は作成
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/setup_' . date('Y-m-d') . '.log';
    $formattedMessage = date('Y-m-d H:i:s') . " [{$level}] - " . $message . PHP_EOL;
    
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    
    // コンソールにも出力
    echo "$level: $message\n";
}

try {
    echo "try ブロック開始\n";
    
    // データベース接続
    $db = Database::getInstance();
    echo "データベース接続完了\n";
    
    logMessage("データベース接続成功");
    
    // トランザクション開始
    $db->beginTransaction();
    echo "トランザクション開始完了\n";
    
    logMessage("トランザクション開始");
    
    // 外部キー制約チェックを無効化
    echo "外部キー制約チェックを無効化\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // 既存のテーブルを削除（存在する場合）
    echo "テーブル削除開始\n";
    logMessage("テーブルの削除を開始");
    
    $db->exec("DROP TABLE IF EXISTS storage_data");
    echo "storage_data テーブル削除完了\n";
    
    $db->exec("DROP TABLE IF EXISTS storage_reports");
    echo "storage_reports テーブル削除完了\n";
    
    $db->exec("DROP TABLE IF EXISTS devices");
    echo "devices テーブル削除完了\n";
    
    // 外部キー制約チェックを再有効化
    echo "外部キー制約チェックを再有効化\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    logMessage("テーブルの削除が完了");
    
    // デバイステーブルの作成
    echo "devicesテーブル作成開始\n";
    logMessage("devicesテーブルを作成");
    
    $db->exec("
        CREATE TABLE devices (
            id VARCHAR(50) PRIMARY KEY,
            device_number INT NOT NULL UNIQUE,
            last_update DATETIME
        )
    ");
    echo "devicesテーブル作成完了\n";
    
    // ストレージデータテーブルの作成
    echo "storage_dataテーブル作成開始\n";
    logMessage("storage_dataテーブルを作成");
    
    $db->exec("
        CREATE TABLE storage_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(50) NOT NULL,
            free_space BIGINT,
            created_at DATETIME,
            FOREIGN KEY (device_id) REFERENCES devices(id)
        )
    ");
    echo "storage_dataテーブル作成完了\n";
    
    // レポートテーブルの作成
    echo "storage_reportsテーブル作成開始\n";
    logMessage("storage_reportsテーブルを作成");
    
    $db->exec("
        CREATE TABLE storage_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            generated_at DATETIME,
            total_devices INT,
            report_data TEXT
        )
    ");
    echo "storage_reportsテーブル作成完了\n";
    
    // インデックスの作成
    echo "インデックス作成開始\n";
    logMessage("インデックスを作成");
    
    $db->exec("CREATE INDEX idx_device_number ON devices (device_number)");
    echo "device_number インデックス作成完了\n";
    
    $db->exec("CREATE INDEX idx_device_id ON storage_data (device_id)");
    echo "device_id インデックス作成完了\n";
    
    $db->exec("CREATE INDEX idx_created_at ON storage_data (created_at)");
    echo "created_at インデックス作成完了\n";
    
    // トランザクション確定
    echo "トランザクションコミット直前\n";
    $db->commit();
    echo "トランザクションコミット完了\n";
    
    logMessage("トランザクションをコミットしました");
    logMessage("データベース初期化が正常に完了しました", "SUCCESS");
    
    echo "スクリプト正常終了\n";
    exit(0);
} catch (Exception $e) {
    echo "例外発生: " . $e->getMessage() . "\n";
    
    // エラー発生時はロールバック
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
        echo "トランザクションロールバック完了\n";
        logMessage("エラーによりトランザクションをロールバックしました", "WARNING");
    }
    
    logMessage("データベース初期化エラー: " . $e->getMessage(), "ERROR");
    
    echo "スクリプトエラー終了\n";
    exit(1);
}