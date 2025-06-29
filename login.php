<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';


    $sql = "SELECT id, nombre, password FROM usuarios WHERE username = ?";
    $stmt = $mysqli->prepare($sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta.']);
        exit;
    }

    $stmt->bind_param("s", $username);

    $stmt->execute();

    $result = $stmt->get_result();
    $foundUser = $result->fetch_assoc(); 

    $stmt->close();
    $mysqli->close();

    if ($foundUser && password_verify($password, $foundUser['password'])) {
        
        session_regenerate_id(true);

        $_SESSION['user_id'] = $foundUser['id'];
        $_SESSION['username'] = $foundUser['nombre'];
        
        echo json_encode(['success' => true]);

    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos.'
        ]);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - UbicaUPIITA</title>
    <link rel="stylesheet" href="./style/login.css">

</head>
<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>
        <form id="login-form">
            <div class="input-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="input-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Ingresar</button>
            <div id="error-message" class="error-message"></div>
        </form>
        <p class="back-link"><a href="index.php">Volver al mapa</a></p>
    </div>

    <script>
        const loginForm = document.getElementById('login-form');
        const errorMessageDiv = document.getElementById('error-message');

        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            errorMessageDiv.textContent = '';

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ username, password })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    window.location.href = 'index.php';
                } else {
                    errorMessageDiv.textContent = result.message || 'Ocurrió un error.';
                }

            } catch (error) {
                console.error('Error en la solicitud de login:', error);
                errorMessageDiv.textContent = 'No se pudo conectar con el servidor.';
            }
        });
    </script>
</body>
</html>