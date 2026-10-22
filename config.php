<?php
/**
 * 公共配置：数据库连接、会话、自动建表与初始化数据导入
 */
declare(strict_types=1);

session_start();

// ====== 后台登录账号（上线前请修改） ======
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123');

// ====== 路径 ======
define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('DB_PATH', DATA_DIR . '/synology.db');
define('SEED_PATH', DATA_DIR . '/seed.json');

// 允许的 DSM 版本清单（后台多选 checkbox 用）
const KNOWN_VERSIONS = ['6.0', '6.1', '6.2', '7.0', '7.1', '7.2'];

// ====== 数据库连接 ======
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

/**
 * 建表 + 首次导入 seed.json（仅当数据库文件不存在时执行）
 */
function bootstrap_db(): void
{
    $fresh = !file_exists(DB_PATH);
    $pdo = db();
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS nas_models (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            arch       TEXT NOT NULL,
            sub_arch   TEXT NOT NULL,
            model      TEXT NOT NULL,
            versions   TEXT NOT NULL DEFAULT '[]',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (arch, sub_arch, model)
        )
    SQL);

    if ($fresh && is_file(SEED_PATH)) {
        $seed = json_decode(file_get_contents(SEED_PATH), true);
        if (is_array($seed) && $seed) {
            $ins = $pdo->prepare(
                'INSERT OR IGNORE INTO nas_models (arch, sub_arch, model, versions)
                 VALUES (:arch, :sub_arch, :model, :versions)'
            );
            $pdo->beginTransaction();
            foreach ($seed as $row) {
                $ins->execute([
                    ':arch'     => $row['arch'] ?? '',
                    ':sub_arch' => $row['sub_arch'] ?? '',
                    ':model'    => $row['model'] ?? '',
                    ':versions' => json_encode(array_values($row['versions'] ?? []), JSON_UNESCAPED_UNICODE),
                ]);
            }
            $pdo->commit();
        }
    }
}

// 页面入口自动执行
bootstrap_db();

// ====== 工具函数 ======
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function model_download_url(string $model, string $version): string
{
    return 'https://www.synology.com/en-global/support/download/'
        . rawurlencode($model) . '?version=' . rawurlencode($version) . '#packages';
}

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
}
