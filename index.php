<?php

require_once 'db.php'; 
session_start();
$usuario_autenticado = isset($_SESSION['user_id']);

$locations = [];
$result_loc = $mysqli->query("SELECT * FROM ubicaciones");
while ($row = $result_loc->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['categoria'] = $row['categoria'] !== null ? (int)$row['categoria'] : null;
    $row['piso'] = (int)$row['piso'];
    $row['cord_x'] = (int)$row['cord_x'];
    $row['cord_y'] = (int)$row['cord_y'];
    $row['punto_de_interes'] = (int)$row['punto_de_interes'];
    $locations[] = $row;
}

$connections = [];
$result_conn = $mysqli->query("SELECT * FROM conexiones");
while ($row = $result_conn->fetch_assoc()) {
    $connections[] = $row;
}

$categories_raw = [];
$result_cat = $mysqli->query("SELECT * FROM categorias");
while ($row = $result_cat->fetch_assoc()) {
    $categories_raw[] = $row;
}


$locationsById = [];
foreach($locations as $location) {
    $locationsById[$location['id']] = $location;
}
$categoriesById = [];
foreach ($categories_raw as $cat) {
    $categoriesById[$cat['id']] = $cat;
}


$historial_completo = [];
if ($usuario_autenticado) {
    $user_id = $_SESSION['user_id'];
    $historial_ids = [];
    
    $sql = "SELECT DISTINCT id_ubicacion FROM (
                SELECT id_ubicacion FROM historial
                WHERE id_usuario = ?
                ORDER BY fecha DESC
            ) AS subquery
            LIMIT 3";
            
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $historial_ids[] = $row['id_ubicacion'];
    }
    $stmt->close();

    if (!empty($historial_ids)) {
        foreach ($historial_ids as $id_lugar) {
            if (isset($locationsById[$id_lugar])) {
                $historial_completo[] = $locationsById[$id_lugar];
            }
        }
    }
}


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
    foreach($locations as $location) { $locationsById[$location['id']] = $location; }
    $distances = []; $previous = [];
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
    $totalDistance = $distances[$destinationNodeId];
    return ['path' => $path, 'distance' => $totalDistance];
}

define('START_NODE_ID', 23);
define('VELOCIDAD_METROS_POR_MINUTO', 50);
$lugares_por_categoria = [];
foreach ($locations as $lugar) {
    if (isset($lugar['categoria']) && is_numeric($lugar['categoria']) && $lugar['categoria'] >= 0) {
        $destino_id = $lugar['id'];
        $resultado_ruta = findShortestPath(START_NODE_ID, $destino_id, $locations, $connections);
        if ($resultado_ruta !== null) {
            $lugar['distancia_calculada'] = $resultado_ruta['distance'];
            $lugar['tiempo_estimado'] = $resultado_ruta['distance'] / VELOCIDAD_METROS_POR_MINUTO;
            $categoria_id = $lugar['categoria'];
            $lugares_por_categoria[$categoria_id][] = $lugar;
        }
    }
}
ksort($lugares_por_categoria);

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UbicaUPIITA</title>
    <link rel="stylesheet" href="./style/global.css">
    
    <script src="https://unpkg.com/konva@9/konva.min.js"></script>
    <script defer src="./index.js" type="text/javascript"></script>
</head>
<body>
    <header>
        <div class="header-container">
            <img src="./assets/logoipn.webp" alt="Logo IPN" class="logo">
            
            

            <h1 class="main-title"><span>Ubica</span><span>UPIITA</span></h1>
            <img src="./assets/logoubicaupiita.jpg" alt="Logo Ubicacion" class="logo"> 
            <div class="session-control">
                <?php if ($usuario_autenticado): ?>
                    <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="logout.php" class="button-logout">Cerrar Sesion</a>
                <?php else: ?>
                    <a href="login.php" class="button-login">Iniciar Sesion</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <section id="map-section" class="map-section">
            <div id="container"></div>
            <div id="map-legend">
                <h4>Simbologia</h4>
                <ul>
                    <li>
                        <span class="legend-symbol point" style="background-color: #0077be;"></span>
                        <span>Inicio / Destino (Planta Baja)</span>
                    </li>
                    <li>
                        <span class="legend-symbol point" style="background-color: #ff8c00;"></span>
                        <span>Inicio / Destino (Piso 1)</span>
                    </li>
                    <li>
                        <span class="legend-symbol point" style="background-color: #32cd32;"></span>
                        <span>Inicio / Destino (Piso 2)</span>
                    </li>
                    <li>
                        <span class="legend-symbol line"></span>
                        <span>Ruta a seguir</span>
                    </li>
                    <li>
                        <span class="legend-symbol ring"></span>
                        <span>Escaleras / Cambio de Piso</span>
                    </li>
                </ul>
            </div>
        </section>
        <section class="search-section">
    <label for="search-input">Buscador</label>
    <div class="search-container">
        <input type="text" id="search-input" placeholder="Buscar por nombre y presionar Enter..." name="search">
        <button id="search-button">Buscar</button>
        <button id="clear-button">Limpiar</button>
    </div>
</section>
        <?php if (!empty($historial_completo)): ?>
            <section class="history-section">
                <h3>Vistos Recientemente</h3>
                <div class="history-grid">
                    <?php foreach ($historial_completo as $item): ?>
                        <article class="history-item location-item" data-id-lugar="<?php echo $item['id']; ?>">
                            <div class="history-photo">
                                <?php
                                if (!empty($item['ruta_fotografia'])) {
                                    echo '<img src="' . htmlspecialchars($item['ruta_fotografia']) . '" alt="' . htmlspecialchars($item['nombre']) . '">';
                                } else {
                                    echo '<span>Sin foto</span>';
                                }
                                ?>
                            </div>
                            <p class="history-name"><?php echo htmlspecialchars($item['nombre']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php
        foreach ($lugares_por_categoria as $id_cat => $lugares_en_categoria):
            $nombre_categoria = isset($categoriesById[$id_cat]) ? $categoriesById[$id_cat]['nombre'] : 'Categoría Desconocida';
        ?>
            <section class="category-section">
                <h2><?php echo htmlspecialchars($nombre_categoria); ?></h2>
                <div class="category-grid">
                    <?php foreach ($lugares_en_categoria as $lugar): ?>
                        <article class="location-item" data-id-lugar="<?php echo $lugar['id']; ?>">
                                <div class="location-photo">
                                    <?php
                                    // Comprobar si la ruta de la fotografía existe y no está vacía
                                    if (!empty($lugar['ruta_fotografia'])) {
                                        // Si existe, mostrar la imagen
                                        echo '<img src="' . htmlspecialchars($lugar['ruta_fotografia']) . '" alt="Foto de ' . htmlspecialchars($lugar['nombre']) . '">';
                                    } else {
                                        // Si no existe, mostrar el texto alternativo
                                        echo '<span class="no-photo">Sin foto</span>';
                                    }
                                    ?>
                                </div>                           
                                <div class="location-details">
                                    <p><?php echo htmlspecialchars($lugar['nombre']); ?></p>
                                    <p>Distancia: <strong><?php echo round($lugar['distancia_calculada']); ?> m</strong></p>
                                    <p>Tiempo: <strong>~<?php echo ceil($lugar['tiempo_estimado']); ?> min</strong></p>
                                </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <div id="route-modal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close">X</button>
            <div class="modal-header">
                <div id="modal-foto" >Foto</div>
                <div class="details">
                    <p id="modal-lugar-nombre">Nombre del Lugar</p>
                    <p id="modal-distancia-total">Distancia</p>
                    <p id="modal-tiempo-total">Tiempo en llegar</p>
                </div>
                <button id="modal-map-button" class="map-button">Marcar en el mapa</button>
            </div>
            
            <h3>Referencias para llegar ahi</h3>
            <ul id="modal-referencias-lista" class="references-list">
                </ul>
        </div>
    </div>

</body>
</html>