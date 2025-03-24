<?php
/**
 * データ受信APIエンドポイント - 最小限のデータのみ保存
 */

require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/utils/utils.php';

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Method not allowed', ['allowed' => 'POST']);
}

// JSONデータを取得
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// JSONデコードエラーチェック
if ($data === null) {
    sendJsonResponse('error', 'Invalid JSON data');
}

// 必須フィールドの検証（デバイス番号と空き容量のみ）
$requiredFields = ['device_number', 'free_space'];
list($isValid, $errorMessage) = validateRequest($requiredFields, $data);

if (!$isValid) {
    sendJsonResponse('error', 'Validation failed: ' . $errorMessage);
}

try {
    // データベース接続
    $db = Database::getInstance();
    
    // デバイス番号を取得
    $deviceNumber = $data['device_number'];
    
    // デバイスIDを生成（内部管理用）
    $deviceId = 'device_' . $deviceNumber;
    
    // 現在の日時
    $timestamp = date('Y-m-d H:i:s');
    
    // トランザクション開始
    $db->beginTransaction();
    
    // デバイスが存在するか確認
    $device = $db->fetchOne(
        "SELECT device_id FROM devices WHERE device_id = ?",
        [$deviceId]
    );
    
    if ($device) {
        // 既存デバイスの更新
        $db->update(
            'devices',
            ['last_update' => $timestamp],
            'device_id = ?',
            [$deviceId]
        );
    } else {
        // 新規デバイスの登録
        $db->insert('devices', [
            'device_id' => $deviceId,
            'device_number' => $deviceNumber,
            'last_update' => $timestamp
        ]);
    }
    
    // ストレージデータの保存（既存データがあれば上書き）
    $storageData = $db->fetchOne(
        "SELECT device_id FROM storage_data WHERE device_id = ?",
        [$deviceId]
    );
    
    if ($storageData) {
        // 既存データの更新
        $db->update(
            'storage_data',
            [
                'free_space' => $data['free_space'],
                'timestamp' => $timestamp
            ],
            'device_id = ?',
            [$deviceId]
        );
    } else {
        // 新規データの挿入
        $db->insert('storage_data', [
            'device_id' => $deviceId,
            'free_space' => $data['free_space'],
            'timestamp' => $timestamp
        ]);
    }
    
    // トランザクション確定
    $db->commit();
    
    sendJsonResponse('success', 'Data received and saved', [
        'device_number' => $deviceNumber,
        'recorded_at' => $timestamp
    ]);
} catch (Exception $e) {
    // エラー発生時はロールバック
    if (isset($db)) {
        $db->rollback();
    }
    
    sendJsonResponse('error', 'Server error: ' . $e->getMessage());
}