<?php
/**
 * 最適化されたデータ受信APIエンドポイント
 * - ログ出力を最小限に抑える
 * - デバイスIDではなくデバイス番号を直接使用
 */

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/utils/utils.php';

// 簡略化されたログ出力機能
if (!function_exists('logMessage')) {
    function logMessage($message, $level = 'INFO') {
        // エラーのみをログに記録
        if ($level === 'ERROR' || $level === 'WARNING') {
            $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs';
            
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/api_' . date('Y-m-d') . '.log';
            $formattedMessage = date('Y-m-d H:i:s') . " [{$level}] - " . $message . PHP_EOL;
            
            file_put_contents($logFile, $formattedMessage, FILE_APPEND);
        }
    }
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', ['allowed' => 'POST']);
}

// JSONデータを取得
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// JSONデコードエラーチェック
if ($data === null) {
    logMessage("不正なJSONデータ", "ERROR");
    sendJsonResponse('error', 'Invalid JSON data');
}

// 必須フィールドの検証
$requiredFields = ['device_number', 'free_space'];
list($isValid, $errorMessage) = validateRequest($requiredFields, $data);

if (!$isValid) {
    logMessage("バリデーションエラー: " . $errorMessage, "ERROR");
    sendJsonResponse('error', 'Validation failed: ' . $errorMessage);
}

try {
    // データベース接続
    $db = Database::getInstance();
    
    // デバイス番号を取得
    $deviceNumber = intval($data['device_number']);
    
    // 現在の日時
    $timestamp = date('Y-m-d H:i:s');
    
    // トランザクション開始
    $db->beginTransaction();
    
    // デバイスが存在するか確認
    $device = $db->fetchOne(
        "SELECT device_number FROM sm_devices WHERE device_number = ?",
        [$deviceNumber]
    );
    
    if ($device) {
        // 既存デバイスの更新
        $db->update(
            'sm_devices',
            ['last_update' => $timestamp],
            'device_number = ?',
            [$deviceNumber]
        );
    } else {
        // 新規デバイスの登録
        $db->insert('sm_devices', [
            'device_number' => $deviceNumber,
            'last_update' => $timestamp
        ]);
    }
    
    // ストレージデータの追加（履歴として保存するため、常に新規挿入）
    $db->insert('sm_storage_data', [
        'device_number' => $deviceNumber,
        'free_space' => intval($data['free_space']),
        'created_at' => $timestamp
    ]);
    
    // トランザクション確定
    $db->commit();
    
    // 成功レスポンスを返す
    sendJsonResponse('success', 'Data received and saved', [
        'device_number' => $deviceNumber,
        'free_space_gb' => bytesToGB(intval($data['free_space'])),
        'recorded_at' => $timestamp
    ]);
} catch (Exception $e) {
    // エラー発生時はロールバック
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    logMessage("エラー発生: " . $e->getMessage(), "ERROR");
    sendJsonResponse('error', 'Server error: ' . $e->getMessage());
}