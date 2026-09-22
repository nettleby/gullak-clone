<?php
/**
 * Backward-compat shim: admin login now lives on the unified login page
 * (login.php?tab=admin). This file only redirects so old bookmarks keep working.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$dest = 'login.php?tab=admin';
if ($next !== '' && !str_contains($next, '://') && !str_contains($next, '..') && !str_contains($next, '\\')) {
    $dest .= '&next=' . urlencode(ltrim($next, '/'));
}
header('Location: ' . url($dest));
exit;
