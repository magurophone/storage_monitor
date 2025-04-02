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
    
    // デバッグ: テーブル構造を確認
    echo "テーブル構造を確認中...\n\n";
    
    // devicesテーブルの構造を表示
    $devicesStructure = $db->fetchAll("DESCRIBE devices");
    echo "Devices テーブルの構造:\n";
    foreach ($devicesStructure as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    echo "\n";
    
    // storage_dataテーブルの構造を表示
    $storageStructure = $db->fetchAll("DESCRIBE storage_data");
    echo "Storage_data テーブルの構造:\n";
    foreach ($storageStructure as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    echo "\n\n";
    
    // 最初に少しデータを表示して確認
    echo "テーブルデータのサンプル:\n";
    $devicesSample = $db->fetchAll("SELECT * FROM devices LIMIT 3");
    foreach ($devicesSample as $device) {
        echo "Device: " . json_encode($device) . "\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}