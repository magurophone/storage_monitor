<?php
// 設定ファイルを読み込み
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// エラーログ設定
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');

// ログにメッセージを記録（上書き方式）
function logMessage($message, $level = 'INFO') {
    // LOG_DIR定数が使用できない場合は直接ディレクトリを指定
    $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs';
    
    // ログディレクトリが存在しない場合は作成
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // 日付なしの固定ファイル名
    $logFile = $logDir . '/report.log';
    $formattedMessage = date('Y-m-d H:i:s') . " [{$level}] - " . $message . PHP_EOL;
    
    // ファイルに書き込む（FILE_APPENDなし = 上書き）
    file_put_contents($logFile, $formattedMessage);
    
    // エラーレベルの場合はPHPのエラーログにも記録
    if ($level === 'ERROR') {
        error_log($message);
    }
}

// 実行開始
logMessage("レポート生成開始");

try {
    // データベース接続
    $db = Database::getInstance();
    logMessage("データベース接続成功");
    
    // 最新のデバイスデータを取得
    $sql = "
        SELECT d.device_number, s.free_space, s.created_at
        FROM devices d
        LEFT JOIN (
            SELECT device_id, free_space, created_at,
                   ROW_NUMBER() OVER (PARTITION BY device_id ORDER BY created_at DESC) as rn
            FROM storage_data
        ) s ON s.device_id = d.id AND s.rn = 1
        ORDER BY d.device_number
    ";
    
    logMessage("SQLクエリ実行: " . str_replace("\n", " ", $sql));
    $devices = $db->fetchAll($sql);
    logMessage("取得したデバイス数: " . count($devices));
    
    // レポートのメタデータを保存
    $reportData = [
        'generated_at' => date('Y-m-d H:i:s'),
        'total_devices' => count($devices)
    ];
    
    logMessage("レポートデータ作成: " . json_encode($reportData));
    $reportId = $db->insert('storage_reports', $reportData);
    logMessage("レポートID作成: " . $reportId);
    
    // 実行完了
    logMessage("レポート生成完了: デバイス数 " . count($devices));
    
} catch (Exception $e) {
    logMessage("エラー: " . $e->getMessage() . "\nトレース: " . $e->getTraceAsString(), "ERROR");
}