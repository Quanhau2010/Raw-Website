<?php
// ============================================================
//  HerlysBoard API — api.php
//  Mỗi paste = 1 file riêng: pastes/{id}.json
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('PASTE_DIR', __DIR__ . '/pastes/');
define('MAX_SIZE',  512 * 1024);

if (!is_dir(PASTE_DIR)) mkdir(PASTE_DIR, 0755, true);

function ok(mixed $data): void {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function pasteFile(string $id): string {
    return PASTE_DIR . preg_replace('/[^a-zA-Z0-9]/', '', $id) . '.json';
}
function readPaste(string $id): ?array {
    $f = pasteFile($id);
    if (!file_exists($f)) return null;
    return json_decode(file_get_contents($f), true);
}
function writePaste(array $p): void {
    file_put_contents(pasteFile($p['id']), json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function deletePasteFile(string $id): void {
    $f = pasteFile($id);
    if (file_exists($f)) unlink($f);
}
function isExpired(array $p): bool {
    return $p['expire'] > 0 && $p['expire'] < time();
}
function uid(int $n = 8): string {
    $c = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $c[random_int(0, strlen($c) - 1)];
    return $s;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$raw    = file_get_contents('php://input');
$body   = [];
if ($raw) $body = json_decode($raw, true) ?? [];
$body = array_merge($_POST, $body);

// CREATE
if ($action === 'create' && $method === 'POST') {
    $title   = trim($body['title']   ?? '');
    $content = $body['content'] ?? '';
    $lang    = $body['lang']    ?? 'plaintext';
    $expSec  = (int)($body['expire'] ?? 0);
    $burn    = (bool)($body['burn']  ?? false);
    $pass    = trim($body['password'] ?? '');

    if (!$content)                   err('content is required');
    if (strlen($content) > MAX_SIZE) err('content exceeds 512KB');

    $id = uid();
    $paste = [
        'id'       => $id,
        'title'    => $title ?: 'Untitled',
        'content'  => $content,
        'lang'     => $lang,
        'expire'   => $expSec > 0 ? time() + $expSec : 0,
        'created'  => time(),
        'views'    => 0,
        'burn'     => $burn,
        'password' => $pass ? password_hash($pass, PASSWORD_DEFAULT) : '',
    ];

    writePaste($paste);
    ok(['id' => $id, 'file' => "pastes/{$id}.json", 'url' => "?id=$id"]);
}

// GET
if ($action === 'get' && $method === 'GET') {
    $id   = trim($_GET['id']       ?? '');
    $pass = trim($_GET['password'] ?? '');
    if (!$id) err('id required');

    $p = readPaste($id);
    if (!$p) err('Paste not found', 404);
    if (isExpired($p)) { deletePasteFile($id); err('Paste has expired', 410); }

    // Password check
    if ($p['password']) {
        if (!$pass) err('password_required', 401);
        if (!password_verify($pass, $p['password'])) err('wrong_password', 403);
    }

    $p['views']++;
    $burned = false;
    if ($p['burn']) {
        deletePasteFile($id);
        $p['burned'] = true;
        $burned = true;
    }
    if (!$burned) writePaste($p);

    // Jangan kirim hash ke client
    unset($p['password']);
    ok($p);
}

// CHECK PASSWORD (chỉ kiểm tra, không trả content)
if ($action === 'checkpass' && $method === 'POST') {
    $id   = trim($body['id']       ?? '');
    $pass = trim($body['password'] ?? '');
    $p    = readPaste($id);
    if (!$p) err('not found', 404);
    if (!$p['password']) ok(['protected' => false]);
    if (!$pass) ok(['protected' => true, 'valid' => false]);
    ok(['protected' => true, 'valid' => password_verify($pass, $p['password'])]);
}

// RAW
if ($action === 'raw' && $method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    $p  = readPaste($id);
    if (!$p || isExpired($p)) {
        http_response_code(404); header('Content-Type: text/plain'); echo 'Not found'; exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $p['content']; exit;
}

// DELETE
if ($action === 'delete') {
    $id = trim($_GET['id'] ?? $body['id'] ?? '');
    if (!$id) err('id required');
    if (!readPaste($id)) err('Paste not found', 404);
    deletePasteFile($id);
    ok(['deleted' => $id]);
}

// LIST
if ($action === 'list' && $method === 'GET') {
    $files = glob(PASTE_DIR . '*.json') ?: [];
    $list  = [];
    foreach ($files as $file) {
        $p = json_decode(file_get_contents($file), true);
        if (!$p) continue;
        if (isExpired($p)) { unlink($file); continue; }
        unset($p['content'], $p['password']);
        $list[] = $p;
    }
    usort($list, fn($a, $b) => $b['created'] - $a['created']);
    ok(array_slice($list, 0, 50));
}

// STATS
if ($action === 'stats' && $method === 'GET') {
    $files      = glob(PASTE_DIR . '*.json') ?: [];
    $totalViews = 0;
    $count      = 0;
    foreach ($files as $file) {
        $p = json_decode(file_get_contents($file), true);
        if (!$p || isExpired($p)) continue;
        $totalViews += (int)$p['views'];
        $count++;
    }
    ok(['total' => $count, 'views' => $totalViews]);
}

err('Unknown action', 405);
