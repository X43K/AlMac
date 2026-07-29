<?php
session_start(); // Necesario para obtener usuario

http_response_code(403);

// CONFIGURACIÓN
$maxIntentos = 3;
$logFile = __DIR__ . "/ips_bloqueadas.log";
$bloqueadasFile = __DIR__ . "/ips_permanentemente_bloqueadas.log";

// Obtener IP real
function getRealIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
}

$ip = getRealIP();
$fecha = date("Y-m-d H:i:s");
$archivo = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
$usuario = $_SESSION['usuario'] ?? 'ANONIMO'; // Usuario logueado

// Verificar si ya está bloqueada permanentemente
$ipsBloqueadas = file_exists($bloqueadasFile) ? file($bloqueadasFile, FILE_IGNORE_NEW_LINES) : [];
$bloqueado = in_array($ip, $ipsBloqueadas);

// Contar intentos anteriores
$intentos = 0;
if (file_exists($logFile)) {
    $lineas = file($logFile);
    foreach ($lineas as $linea) {
        if (strpos($linea, "IP: $ip ") !== false) {
            $intentos++;
        }
    }
}

// Si supera límite y no está aún en bloqueadas → añadir
if ($intentos >= $maxIntentos && !$bloqueado) {
    file_put_contents($bloqueadasFile, $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
    $bloqueado = true;
}

// Registrar intento actual
$registro = "[$fecha] IP: $ip | Usuario: $usuario | Archivo: $archivo | UA: $userAgent" . PHP_EOL;
file_put_contents($logFile, $registro, FILE_APPEND | LOCK_EX);

// Obtener país
$pais = "Desconocido";
$codigoPais = "";
$geo = @json_decode(file_get_contents("http://ip-api.com/json/$ip"));
if ($geo && $geo->status === "success") {
    $pais = $geo->country;
    $codigoPais = strtolower($geo->countryCode);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 - Acceso Restringido</title>

<link rel="icon" href="images/logo.webp">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" sizes="180x180" href="images/logo.webp">
<link rel="stylesheet" href="style.css">

<meta http-equiv="refresh" content="7;url=index.php">
<style>
body{font-family: Arial, sans-serif;background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);height:100vh;display:flex;justify-content:center;align-items:center;color:white;}
.container{background:rgba(0,0,0,0.8);padding:40px;border-radius:12px;text-align:center;width:90%;max-width:600px;}
h1{color:#ff4c4c;font-size:60px;}
.ip-box{margin-top:20px;padding:12px;background:rgba(255,0,0,0.2);border:1px solid #ff4c4c;border-radius:8px;}
.bloqueado{margin-top:20px;padding:12px;background:rgba(255,0,0,0.4);border:1px solid red;border-radius:8px;font-weight:bold;}
.btn{display:inline-block;margin-top:25px;padding:10px 20px;background:#ff4c4c;color:white;text-decoration:none;border-radius:20px;}
</style>
</head>
<body>
<div class="container">
<img src="images/logo.webp" width="40" class="logo" alt="Logo">
<h1>403</h1>
<h2>Acceso Restringido</h2>
<div class="ip-box">
⚠ Su intento ha sido registrado.<br>
IP detectada: <strong><?php echo htmlspecialchars($ip); ?></strong>
<?php if($codigoPais): ?>
<img src="https://flagcdn.com/24x18/<?php echo $codigoPais; ?>.png">
<?php endif; ?>
<br>
País: <?php echo htmlspecialchars($pais); ?>
<br>
Usuario: <strong><?php echo htmlspecialchars($usuario); ?></strong>
</div>

<?php if($bloqueado): ?>
<div class="bloqueado">
🚫 SU DIRECCIÓN IP HA SIDO BLOQUEADA PERMANENTEMENTE<br>
Intentos detectados: <?php echo $intentos; ?>
</div>
<?php else: ?>
<a href="index.php" class="btn">Volver al inicio</a>
<?php endif; ?>

</div>
</body>
</html>