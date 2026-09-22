<?php
/**
 * PDO singleton. Every page/script gets the same connection.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // keep all DB timestamps in IST, same as PHP
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:24px;'
                . 'border:1px solid #f3e8d7;border-radius:12px;background:#fffbeb;color:#7c2d12">'
                . '<h2 style="margin:0 0 8px">Database connection failed</h2>'
                . '<p style="margin:0 0 12px">Check <code>config/config.php</code> and make sure MySQL is running'
                . ' and the database <b>gullak</b> exists (import <code>db/schema.sql</code>).</p>'
                . (DISPLAY_ENV ? '<pre style="white-space:pre-wrap;background:#fff;padding:8px;border-radius:6px">'
                    . htmlspecialchars($e->getMessage()) . '</pre>' : '')
                . '</div>');
        }
    }
    return $pdo;
}
