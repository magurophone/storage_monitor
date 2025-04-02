<?php
/**
 * データ受信APIエンドポイント - 修正版
 */

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/utils/utils.php';

// ログ出力機能の追加
if (!function_exists('logMessage')) {
    function logMessage($message, $level = 'INFO') {
        $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs';
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/api_' . date('Y-m-d') . '.log';
        $formattedMessage = date('Y-m-d H:i:s') . " [{$level}] - " . $message . PHP_EOL;
        
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }
}

// リクエストの開始をログに記録
logMessage("APIリクエスト受信: " . $_SERVER['REMOTE_ADDR']);

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage("不正なメソッド: " . $_SERVER['REQUEST_METHOD'], "WARNING");
    sendJsonResponse('error', 'Method not allowed', ['allowed' => 'POST']);
}

// JSONデータを取得
$jsonData = file_get_contents('php://input');
logMessage("受信データ: " . $jsonData);

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
    
    // デバイスIDを生成（内部管理用）
    $deviceId = 'device_' . $deviceNumber;
    
    // 現在の日時
    $timestamp = date('Y-m-d H:i:s');
    
    // トランザクション開始
    $db->beginTransaction();
    logMessage("デバイス番号 {$deviceNumber} のデータ処理を開始");
    
    // デバイスが存在するか確認
    $device = $db->fetchOne(
        "SELECT id FROM devices WHERE device_number = ?",
        [$deviceNumber]
    );
    
    if ($device) {
        // 既存デバイスの更新
        logMessage("既存デバイスを更新: デバイス番号 {$deviceNumber}");
        $db->update(
            'devices',
            ['last_update' => $timestamp],
            'id = ?',
            [$device['id']]
        );
        $deviceId = $device['id'];
    } else {
        // 新規デバイスの登録
        logMessage("新規デバイスを登録: デバイス番号 {$deviceNumber}");
        $db->insert('devices', [
            'id' => $deviceId,
            'device_number' => $deviceNumber,
            'last_update' => $timestamp
        ]);
    }
    
    // ストレージデータの追加（履歴として保存するため、常に新規挿入）
    logMessage("ストレージデータを追加: デバイス番号 {$deviceNumber}, 空き容量 {$data['free_space']}");
    $db->insert('storage_data', [
        'device_id' => $deviceId,
        'free_space' => intval($data['free_space']),
        'created_at' => $timestamp
    ]);
    
    // トランザクション確定
    $db->commit();
    logMessage("データ処理完了: デバイス番号 {$deviceNumber}");
    
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