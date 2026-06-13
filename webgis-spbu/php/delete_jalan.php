<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = (int)($data['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "ID tidak valid"]);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM jalan_lines WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(["success" => true, "message" => "Data jalan berhasil dihapus"]);