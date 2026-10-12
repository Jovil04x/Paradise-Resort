<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';
require_admin();
$pdo = get_pdo();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['approve','reject','cancel'], true)) {
        $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
        $stmt->execute([$action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'cancelled'), $id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

// List
$rows = $pdo->query("SELECT b.*, u.full_name, u.email, r.name AS room_name FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id ORDER BY b.created_at DESC")->fetchAll();
echo json_encode(['ok'=>true,'rows'=>$rows]);

