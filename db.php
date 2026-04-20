<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function vault_env($key, $default = '')
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

$dbHost = vault_env('ARCADIA_DB_HOST', 'localhost');
$dbUser = vault_env('ARCADIA_DB_USER', 'root');
$dbPass = vault_env('ARCADIA_DB_PASS', '');
$dbName = vault_env('ARCADIA_DB_NAME', 'arcadia_vault');

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function vault_upper($value)
{
    $value = (string) $value;
    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

function vault_substr($value, $start, $length = null)
{
    $value = (string) $value;

    if (function_exists('mb_substr')) {
        return $length === null
            ? mb_substr($value, $start, null, 'UTF-8')
            : mb_substr($value, $start, $length, 'UTF-8');
    }

    return $length === null
        ? substr($value, $start)
        : substr($value, $start, $length);
}

function redirect_to_home()
{
    header("Location: collection.php");
    exit;
}

function set_flash_message($type, $message)
{
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][] = [
        'type' => (string) $type,
        'message' => (string) $message,
    ];
}

function get_flash_messages()
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}

function redirect_with_flash($location, $type, $message)
{
    set_flash_message($type, $message);
    header('Location: ' . $location);
    exit;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function require_csrf_token($token)
{
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function normalize_status($status)
{
    $allowed = ['Not Started', 'In Progress', 'Completed'];
    return in_array($status, $allowed, true) ? $status : 'Not Started';
}

function normalize_rating($rating)
{
    $rating = (int) $rating;
    if ($rating < 1) return 1;
    if ($rating > 5) return 5;
    return $rating;
}

function normalize_personal_notes($notes)
{
    $notes = trim((string) $notes);
    if ($notes === '') return '';
    $normalized = strtolower(preg_replace('/\s+/', ' ', $notes));
    $emptyAliases = ['n/a', 'na', 'none', 'not applicable'];
    return in_array($normalized, $emptyAliases, true) ? '' : $notes;
}

function validate_vault_payload($source, $existingTitle = '')
{
    $data = [
        'Game_Title' => trim((string) ($source['Game_Title'] ?? $existingTitle)),
        'Platform' => trim((string) ($source['Platform'] ?? '')),
        'Creator' => trim((string) ($source['Creator'] ?? '')),
        'Acquisition_Date' => trim((string) ($source['Acquisition_Date'] ?? '')),
        'Current_Rank' => trim((string) ($source['Current_Rank'] ?? '')),
        'Prime_Rank' => trim((string) ($source['Prime_Rank'] ?? '')),
        'Rating' => normalize_rating($source['Rating'] ?? 3),
        'Hours_Played' => max(0, (int) ($source['Hours_Played'] ?? 0)),
        'Progress_Status' => normalize_status($source['Progress_Status'] ?? 'Not Started'),
        'Personal_Notes' => normalize_personal_notes($source['Personal_Notes'] ?? ''),
    ];

    $errors = [];

    if ($data['Game_Title'] === '') {
        $errors[] = 'Game title is required.';
    }
    if ($data['Platform'] === '') {
        $errors[] = 'Platform is required.';
    }
    if ($data['Creator'] === '') {
        $errors[] = 'Creator or studio is required.';
    }
    if ($data['Current_Rank'] === '') {
        $errors[] = 'Current rank is required.';
    }
    if ($data['Prime_Rank'] === '') {
        $errors[] = 'Prime rank is required.';
    }

    $acquisitionDate = $data['Acquisition_Date'];
    if ($acquisitionDate !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $acquisitionDate);
        $isValid = $d && $d->format('Y-m-d') === $acquisitionDate;
        if (!$isValid) {
            $errors[] = 'Date added must use a valid date.';
            $data['Acquisition_Date'] = '';
        }
    }

    return [$data, $errors];
}

function has_personal_notes($notes)
{
    return normalize_personal_notes($notes) !== '';
}

function render_stars($rating)
{
    $rating = normalize_rating($rating);
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function format_date($date)
{
    if (!$date || $date === '0000-00-00') return '-';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('M j, Y') : '-';
}

function days_since($date)
{
    if (!$date || $date === '0000-00-00') return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d) return null;
    $now = new DateTime();
    return (int) $now->diff($d)->days;
}

function get_vault_game_presets()
{
    return [
        'mobilelegends' => [
            'title' => 'Mobile Legends',
            'platform' => 'Mobile',
            'creator' => 'Moonton',
            'image' => 'assets/games/mobile-legends.jpg',
        ],
        'mobilelegend' => [
            'title' => 'Mobile Legends',
            'platform' => 'Mobile',
            'creator' => 'Moonton',
            'image' => 'assets/games/mobile-legends.jpg',
        ],
        'callofduty' => [
            'title' => 'Call of Duty',
            'platform' => 'Mobile',
            'creator' => 'Activision',
            'image' => 'assets/games/call-of-duty.jpg',
        ],
        'crossfire' => [
            'title' => 'Crossfire',
            'platform' => 'PC',
            'creator' => 'Smilegate',
            'image' => 'assets/games/crossfire.jpg',
        ],
        'clashofclans' => [
            'title' => 'Clash of Clans',
            'platform' => 'Mobile',
            'creator' => 'Supercell',
            'image' => 'assets/games/clash-of-clans.jpg',
        ],
        'dota' => [
            'title' => 'Dota',
            'platform' => 'PC',
            'creator' => 'Valve',
            'image' => 'assets/games/dota-2.jpg',
        ],
        'dota2' => [
            'title' => 'Dota',
            'platform' => 'PC',
            'creator' => 'Valve',
            'image' => 'assets/games/dota-2.jpg',
        ],
    ];
}

function normalize_game_key($title)
{
    $title = strtolower(trim((string) $title));
    return preg_replace('/[^a-z0-9]+/', '', $title);
}

function get_vault_default_image($title)
{
    $presets = get_vault_game_presets();
    $key = normalize_game_key($title);
    return $presets[$key]['image'] ?? null;
}

function get_vault_uploaded_image($entryId)
{
    $entryId = (int) $entryId;
    if ($entryId <= 0) return null;

    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $path = $baseDir . DIRECTORY_SEPARATOR . 'vault-' . $entryId . '.' . $ext;
        if (is_file($path)) {
            return 'assets/uploads/vault-' . $entryId . '.' . $ext;
        }
    }

    return null;
}

function get_vault_image($title, $entryId = 0)
{
    return get_vault_uploaded_image($entryId) ?? get_vault_default_image($title);
}

function delete_vault_uploaded_image($entryId)
{
    $entryId = (int) $entryId;
    if ($entryId <= 0) return;

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $path = $uploadDir . DIRECTORY_SEPARATOR . 'vault-' . $entryId . '.' . $ext;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function save_vault_image_upload($file, $entryId)
{
    $entryId = (int) $entryId;
    if ($entryId <= 0 || !isset($file) || !is_array($file)) return [null, null];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return [null, 'The image upload could not be completed.'];
    if (!is_uploaded_file($file['tmp_name'] ?? '')) return [null, 'The uploaded image could not be verified.'];

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $maxBytes = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        return [null, 'Images must be 5 MB or smaller.'];
    }

    $tmpName = $file['tmp_name'];
    $imageInfo = @getimagesize($tmpName);
    if (!$imageInfo || empty($imageInfo['mime'])) {
        return [null, 'Only valid JPG, PNG, or WebP images are allowed.'];
    }

    $mime = $imageInfo['mime'];
    if (!isset($allowed[$mime])) {
        return [null, 'Only JPG, PNG, or WebP images are allowed.'];
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            return [null, 'The upload folder could not be prepared.'];
        }
    }

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $oldExt) {
        $oldPath = $uploadDir . DIRECTORY_SEPARATOR . 'vault-' . $entryId . '.' . $oldExt;
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    $ext = $allowed[$mime];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . 'vault-' . $entryId . '.' . $ext;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        return [null, 'The image could not be saved to the vault.'];
    }

    return ['assets/uploads/vault-' . $entryId . '.' . $ext, null];
}

function execute_or_fail($statement, $failureMessage)
{
    if (!$statement) {
        throw new RuntimeException($failureMessage);
    }

    if (!$statement->execute()) {
        throw new RuntimeException($failureMessage);
    }
}
?>
