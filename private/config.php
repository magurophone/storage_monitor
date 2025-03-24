<?php
// データベース設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'storage_monitor');
define('DB_USER', 'root');
define('DB_PASS', '');  // XAMPPのデフォルトではパスワードなし

// ログディレクトリ
define('LOG_DIR', __DIR__ . '/../logs');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// ログディレクトリが存在しない場合は作成
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}