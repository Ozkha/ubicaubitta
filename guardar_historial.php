<?php
session_start();

require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_lugar = $data['id'] ?? null;

if (empty($id_lugar) || !is_numeric($id_lugar)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'ID de lugar inválido.']);
    exit;
}


$current_user_id = $_SESSION['user_id'];

$sql_delete_dupe = "DELETE FROM historial WHERE id_usuario = ? AND id_ubicacion = ?";
$stmt = $mysqli->prepare($sql_delete_dupe);
$stmt->bind_param("ii", $current_user_id, $id_lugar);
$stmt->execute();
$stmt->close();

$sql_insert = "INSERT INTO historial (id_usuario, id_ubicacion) VALUES (?, ?)";
$stmt = $mysqli->prepare($sql_insert);
$stmt->bind_param("ii", $current_user_id, $id_lugar);
$stmt->execute();
$stmt->close();

$result = $mysqli->query("SELECT count(*) as total FROM historial WHERE id_usuario = $current_user_id");
$count = $result->fetch_assoc()['total'];

if ($count > 3) {
    $limit = $count - 3;
    $sql_cleanup = "DELETE FROM historial WHERE id_usuario = ? ORDER BY id ASC LIMIT ?";
    $stmt = $mysqli->prepare($sql_cleanup);
    $stmt->bind_param("ii", $current_user_id, $limit);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true, 'message' => 'Historial actualizado.']);

$mysqli->close();
?>