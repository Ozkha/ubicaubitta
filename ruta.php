<?php
require_once 'db.php';

header('Content-Type: application/json');

const START_NODE_ID = 23;

function findShortestPath($startNodeId, $destinationNodeId, $locations, $connections) {
    $graph = [];
    foreach ($connections as $conn) {
        $origen = $conn['id_punto_origen'];
        $destino = $conn['id_punto_destino'];
        $distancia = (float)$conn['distancia'];
        $graph[$origen][] = ['node' => $destino, 'weight' => $distancia];
        $graph[$destino][] = ['node' => $origen, 'weight' => $distancia];
    }
    
    $locationsById = [];
    foreach($locations as $location) {
        $locationsById[$location['id']] = $location;
    }

    $distances = [];
    $previous = [];
    $queue = new SplPriorityQueue();

    foreach ($locationsById as $nodeId => $location) {
        $distances[$nodeId] = INF;
        $previous[$nodeId] = null;
    }

    $distances[$startNodeId] = 0;
    $queue->insert($startNodeId, 0);

    while (!$queue->isEmpty()) {
        $currentNode = $queue->extract();
        if ($currentNode == $destinationNodeId) break;
        if (!isset($graph[$currentNode])) continue;

        foreach ($graph[$currentNode] as $neighbor) {
            $neighborNode = $neighbor['node'];
            if (!isset($distances[$neighborNode])) continue;
            $weight = $neighbor['weight'];
            $newDist = $distances[$currentNode] + $weight;
            if ($newDist < $distances[$neighborNode]) {
                $distances[$neighborNode] = $newDist;
                $previous[$neighborNode] = $currentNode;
                $queue->insert($neighborNode, -$newDist);
            }
        }
    }

    $path = [];
    $current = $destinationNodeId;
    if($previous[$current] === null && $current != $startNodeId) return null;

    while ($current !== null) {
        array_unshift($path, $current);
        $current = $previous[$current] ?? null;
    }
    
    if (empty($path) || $path[0] != $startNodeId) return null;

    $fullPathDetails = [];
    foreach($path as $nodeId) {
        if(isset($locationsById[$nodeId])) {
            $loc = $locationsById[$nodeId];
            $loc['id'] = (int)$loc['id'];
            $loc['piso'] = (int)$loc['piso'];
            $loc['cord_x'] = (int)$loc['cord_x'];
            $loc['cord_y'] = (int)$loc['cord_y'];
            $loc['punto_de_interes'] = (int)$loc['punto_de_interes'];
            $fullPathDetails[] = $loc;
        }
    }

    $totalDistance = $distances[$destinationNodeId];
    return ['path' => $fullPathDetails, 'distance' => $totalDistance];
}


if (!isset($_GET['destino']) || !is_numeric($_GET['destino'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "destino" no proporcionado o inválido.']);
    exit;
}
$destinationNodeId = (int)$_GET['destino'];



$locations = [];
$result_locations = $mysqli->query("SELECT * FROM ubicaciones");
if ($result_locations) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron obtener los datos de ubicaciones.']);
    exit;
}

$connections = [];
$result_connections = $mysqli->query("SELECT * FROM conexiones");
if ($result_connections) {
    while ($row = $result_connections->fetch_assoc()) {
        $connections[] = $row;
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron obtener los datos de conexiones.']);
    exit;
}



$result = findShortestPath(START_NODE_ID, $destinationNodeId, $locations, $connections);

if ($result !== null) {
    $response = [
        'ruta' => $result['path'],
        'distancia_total' => round($result['distance'], 2) 
    ];
    echo json_encode($response);
} else {
    http_response_code(404);
    echo json_encode(['error' => "No se encontró una ruta desde el punto de inicio (ID: " . START_NODE_ID . ") al destino (ID: " . $destinationNodeId . ")."]);
}

$mysqli->close();
?>