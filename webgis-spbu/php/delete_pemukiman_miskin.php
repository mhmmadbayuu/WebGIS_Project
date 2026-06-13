<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = (int)($data['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "ID tidak valid"]);
    exit;
}

try {
    // best-effort delete uploaded proof file
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
        if (in_array('bukti_file', $cols, true)) {
            $stmt = $pdo->prepare("SELECT bukti_file FROM pemukiman_miskin_points WHERE id = ?");
            $stmt->execute([$id]);
            $path = $stmt->fetchColumn();
            if ($path && is_string($path) && strpos($path, 'uploads/bukti/') === 0) {
                $full = __DIR__ . '/../' . $path;
                if (is_file($full)) @unlink($full);
            }
        }
    } catch (Exception $e) { /* ignore */ }

    $stmt = $pdo->prepare("DELETE FROM pemukiman_miskin_points WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["success" => true, "message" => "Pemukiman miskin berhasil dihapus"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
