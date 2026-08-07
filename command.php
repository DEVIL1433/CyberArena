<?php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/engine.php';

if(!isset($_SESSION['g'])) $_SESSION['g'] = initGame();
$g = &$_SESSION['g'];

$action = $_POST['action'] ?? 'cmd';
$lines = [];
$widget = null;
$cleared = false;

if($action === 'boot'){
    $_SESSION['g'] = initGame();
    $g = &$_SESSION['g'];
    outp($lines, 'CYBERARENA', 'out-ok');
    outp($lines, 'Last login: '.date('D M j H:i:s Y', time()-3600).' from 10.0.2.15', 'out-muted');
    outp($lines, 'Authorized users only. All activity is logged and monitored.', 'out-muted');
} elseif($action === 'sqli'){
    handleSqliAttempt($g, $lines, $_POST['host'] ?? '', $_POST['user'] ?? '', $_POST['pass'] ?? '');
} else {
    $cmdText = $_POST['cmd'] ?? '';
    if(!$g['awaitingPassword'] && trim($cmdText) !== ''){
        $g['history'][] = $cmdText;
    }
    [$lines, $widget, $cleared] = runCommand($g, $cmdText);
}

$status = statusPayload($g);

echo json_encode([
    'lines' => $lines,
    'widget' => $widget,
    'cleared' => $cleared,
    'status' => $status,
]);
