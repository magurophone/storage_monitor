<?php
/**
 * データベース接続・操作クラス
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    /**
     * コンストラクタ - PDO接続を初期化
     */
    private function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            if (function_exists('logMessage')) {
                logMessage("データベース接続確立: " . DB_HOST);
            }
        } catch (PDOException $e) {
            if (function_exists('logMessage')) {
                logMessage("データベース接続失敗: " . $e->getMessage(), "ERROR");
            }
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * シングルトンインスタンスを取得
     * 
     * @return Database インスタンス
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * SQLクエリを実行
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return PDOStatement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            if (function_exists('logMessage')) {
                logMessage("SQL実行成功: " . substr(str_replace("\n", " ", $sql), 0, 100) . 
                           (strlen($sql) > 100 ? "..." : ""));
            }
            
            return $stmt;
        } catch (PDOException $e) {
            if (function_exists('logMessage')) {
                $paramInfo = empty($params) ? "なし" : json_encode($params);
                logMessage("SQL実行エラー: " . $e->getMessage() . 
                           "\nSQL: " . str_replace("\n", " ", $sql) . 
                           "\nパラメータ: " . $paramInfo, "ERROR");
            }
            throw $e;
        }
    }
    
    /**
     * 単一行を取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array|null 結果の連想配列または該当データがない場合はnull
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        
        if (function_exists('logMessage')) {
            logMessage("fetchOne結果: " . ($result ? "データあり" : "データなし"));
        }
        
        return $result;
    }
    
    /**
     * 複数行を取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array 結果の連想配列の配列
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $results = $stmt->fetchAll();
        
        if (function_exists('logMessage')) {
            logMessage("fetchAll結果: " . count($results) . "件のデータを取得");
        }
        
        return $results;
    }
    
    /**
     * データを挿入
     * 
     * @param string $table テーブル名
     * @param array $data 挿入するデータの連想配列
     * @return int 挿入されたレコードのID
     */
    public function insert($table, $data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $this->query($sql, array_values($data));
            $lastId = $this->pdo->lastInsertId();
            
            if (function_exists('logMessage')) {
                logMessage("挿入成功: テーブル {$table}, ID {$lastId}");
            }
            
            return $lastId;
        } catch (PDOException $e) {
            if (function_exists('logMessage')) {
                logMessage("挿入エラー: テーブル {$table}, " . $e->getMessage() . 
                           "\nデータ: " . json_encode($data), "ERROR");
            }
            throw $e;
        }
    }
    
    /**
     * データを更新
     * 
     * @param string $table テーブル名
     * @param array $data 更新するデータの連想配列
     * @param string $where WHERE句
     * @param array $params WHERE句のパラメータ配列
     * @return int 更新された行数
     */
    public function update($table, $data, $where, $params = []) {
        try {
            $set = [];
            foreach (array_keys($data) as $column) {
                $set[] = "{$column} = ?";
            }
            $set = implode(', ', $set);
            
            $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
            $stmt = $this->query($sql, array_merge(array_values($data), $params));
            $rowCount = $stmt->rowCount();
            
            if (function_exists('logMessage')) {
                logMessage("更新成功: テーブル {$table}, {$rowCount}行更新");
            }
            
            return $rowCount;
        } catch (PDOException $e) {
            if (function_exists('logMessage')) {
                logMessage("更新エラー: テーブル {$table}, " . $e->getMessage() . 
                           "\nデータ: " . json_encode($data) . 
                           "\nWHERE: " . $where, "ERROR");
            }
            throw $e;
        }
    }
    
    /**
     * データを削除
     * 
     * @param string $table テーブル名
     * @param string $where WHERE句
     * @param array $params WHERE句のパラメータ配列
     * @return int 削除された行数
     */
    public function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->query($sql, $params);
            $rowCount = $stmt->rowCount();
            
            if (function_exists('logMessage')) {
                logMessage("削除成功: テーブル {$table}, {$rowCount}行削除");
            }
            
            return $rowCount;
        } catch (PDOException $e) {
            if (function_exists('logMessage')) {
                logMessage("削除エラー: テーブル {$table}, " . $e->getMessage() . 
                           "\nWHERE: " . $where, "ERROR");
            }
            throw $e;
        }
    }
    
    /**
     * SQLを直接実行
     * 
     * @param string $sql SQL文
     * @return int 影響を受けた行数
     */
    public function exec($sql) {
        return $this->pdo->exec($sql);
    }
    
    /**
     * トランザクションがアクティブかどうかを確認
     * 
     * @return bool トランザクションがアクティブならtrue
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
     * トランザクションを開始
     */
    public function beginTransaction() {
        $this->pdo->beginTransaction();
        
        if (function_exists('logMessage')) {
            logMessage("トランザクション開始");
        }
    }
    
    /**
     * トランザクションをコミット
     */
    public function commit() {
        $this->pdo->commit();
        
        if (function_exists('logMessage')) {
            logMessage("トランザクションコミット");
        }
    }
    
    /**
     * トランザクションをロールバック
     */
    public function rollback() {
        $this->pdo->rollBack();
        
        if (function_exists('logMessage')) {
            logMessage("トランザクションロールバック", "WARNING");
        }
    }
}