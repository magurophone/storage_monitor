<?php
/**
 * 空き容量データをシンプルなテキスト形式で出力
 */

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    // データベース接続
    $db = Database::getInstance();
    
    // デバイスとそれに関連する最新の容量データを表示
    $devices = $db->fetchAll("
        SELECT d.id, d.device_number, d.last_update, s.free_space
        FROM devices d
        LEFT JOIN (
            SELECT device_id, free_space, created_at,
                   ROW_NUMBER() OVER (PARTITION BY device_id ORDER BY created_at DESC) as rn
            FROM storage_data
        ) s ON s.device_id = d.id AND s.rn = 1
        ORDER BY d.device_number
    ");
    
    foreach ($devices as $device) {
        $freeSpaceGB = isset($device['free_space']) ? round($device['free_space'] / (1024 * 1024 * 1024), 2) : 'N/A';
        echo "Device #" . $device['device_number'] . " - 最終更新: " . $device['last_update'] . " - 空き容量: " . $freeSpaceGB . " GB\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}