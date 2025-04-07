<?php
/**
 * エクセル用のシンプルなテキスト形式でデータをエクスポート
 * 形式: 端末番号,空き容量(GB),日時
 * エクセルのC7～C26、H7～H26に対応（端末番号01～40）
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
    
    // データを整理（端末番号をキーとする配列に変換）
    $deviceData = [];
    foreach ($data as $row) {
        $deviceNumber = $row['device_number'];
        $freeSpace = $row['free_space'];
        $freeSpaceGB = round($freeSpace / (1024 * 1024 * 1024), 2);
        $date = $row['created_at'];
        
        $deviceData[$deviceNumber] = [
            'free_space' => $freeSpaceGB,
            'date' => $date
        ];
    }
    
    // 1行目: バージョン情報とタイムスタンプ
    echo "ストレージモニター データエクスポート - " . date('Y-m-d H:i:s') . "\n";
    
    // 2行目: 区切り線
    echo "----------------------------------------\n";
    
    // 3行目: ヘッダー
    echo "端末番号,空き容量(GB),更新日時\n";
    
    // データ行: 端末番号1～40までループ（存在しない端末はハイフン表示）
    for ($i = 1; $i <= 40; $i++) {
        $formattedDeviceNumber = sprintf('%02d', $i); // 01, 02, ...の形式
        
        if (isset($deviceData[$i])) {
            echo "{$formattedDeviceNumber},{$deviceData[$i]['free_space']},{$deviceData[$i]['date']}\n";
        } else {
            echo "{$formattedDeviceNumber},-,-\n";
        }
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}