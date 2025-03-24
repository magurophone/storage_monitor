<?php
/**
 * ユーティリティ関数
 * receive_data.php と generate_report.php で使用される共通関数
 */

/**
 * JSON形式のレスポンスを送信
 * 
 * @param string $status 'success' または 'error'
 * @param string $message レスポンスメッセージ
 * @param array $data 追加データ (オプション)
 */
function sendJsonResponse($status, $message, $data = []) {
    // レスポンスのHTTPヘッダーを設定
    header('Content-Type: application/json');
    
    // ステータスコードの設定
    if ($status === 'error') {
        http_response_code(400); // Bad Request
    } else {
        http_response_code(200); // OK
    }
    
    // レスポンスデータの構築
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    // 追加データがあれば結合
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    // JSONエンコードして出力
    echo json_encode($response);
    exit;
}

/**
 * リクエストの必須フィールドを検証
 * 
 * @param array $requiredFields 必須フィールドの配列
 * @param array $data 検証するデータ
 * @return array [検証結果(bool), エラーメッセージ(string)]
 */
function validateRequest($requiredFields, $data) {
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return [false, "Missing required field: {$field}"];
        }
    }
    
    return [true, ""];
}

/**
 * 空き容量をGB単位に変換（小数点以下2桁）
 * 
 * @param int $bytes 空き容量（バイト）
 * @return float GB単位での空き容量
 */
function bytesToGB($bytes) {
    return round($bytes / (1024 * 1024 * 1024), 2);
}