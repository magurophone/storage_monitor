<?php
/**
 * シンプルなテキスト形式でデータをエクスポート
 */

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    // データベース接続
    $db = Database::getInstance();
    
    // デバイスとそれに関連する最新の容量データを取得
    $data = $db->fetchAll("
        SELECT sd.device_number, sd.free_space, sd.created_at
        FROM sm_storage_data sd
        INNER JOIN (
            SELECT device_number, MAX(created_at) as latest_date
            FROM sm_storage_data
            GROUP BY device_number
        ) latest ON sd.device_number = latest.device_number AND sd.created_at = latest.latest_date
        ORDER BY sd.device_number
    ");
    
    // データを出力
    foreach ($data as $row) {
        $deviceNumber = $row['device_number'];
        $freeSpace = $row['free_space'];
        $freeSpaceGB = round($freeSpace / (1024 * 1024 * 1024), 2);
        $date = $row['created_at'];
        
        echo "端末番号: {$deviceNumber} - 空き容量: {$freeSpaceGB} GB - 日時: {$date}\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}