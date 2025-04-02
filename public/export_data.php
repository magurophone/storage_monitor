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
    
    // 最新の空き容量データを取得（最小限の情報のみ）
    $query = "
        SELECT 
            d.device_number,
            s.free_space
        FROM 
            devices d
        LEFT JOIN 
            storage_data s ON d.device_id = s.device_id
        ORDER BY 
            d.device_number ASC
    ";
    
    $devices = $db->fetchAll($query);
    
    // テキスト形式で出力（GBに変換）
    foreach ($devices as $device) {
        $freeSpaceGB = round($device['free_space'] / (1024 * 1024 * 1024), 2);
        echo "【{$device['device_number']}】 {$freeSpaceGB}\n";
    }
    // バッファの内容を取得
    $output = ob_get_clean();
    
    // ブラウザにも表示
    echo $output;
    
    // ファイルに書き込み
    $filename = __DIR__ . '/../data/storage_data.txt';
    file_put_contents($filename, $output);
    
    echo "\n\nデータを " . $filename . " に保存しました。";
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}