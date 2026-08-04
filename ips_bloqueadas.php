<?php
session_name('ALMAC_SESSID');
session_start();

// =====================================================
// CONTROL DE SESIÓN ALMACÉN
// =====================================================

if(
    !isset($_SESSION["usuario"])
    ||
    !in_array(
        $_SESSION["usuario"]["rol"],
        ["admin","superadmin"]
    )
){

    header("Location: login.php");
    exit;

}


$is_superadmin = 
    $_SESSION["usuario"]["rol"]=="superadmin";


$is_admin =
    in_array(
        $_SESSION["usuario"]["rol"],
        ["admin","superadmin"]
    );


$usuario_nombre = $_SESSION["usuario"]["nombre"] ?? "";
$usuario_login  = $_SESSION["usuario"]["usuario"] ?? "";

/* =====================================================
   ARCHIVOS DE SEGURIDAD
   ===================================================== */

$ips_file = __DIR__ . "/ips_permanentemente_bloqueadas.log";

$ips_registro = __DIR__ . "/ips_bloqueadas.log";
// Procesar desbloqueo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['desbloquear_ip'])) {
    $ip_a_desbloquear = $_POST['desbloquear_ip'];
    function eliminarIP($archivo, $ip) {
        if(!file_exists($archivo)) return;
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lineas = array_filter($lineas, fn($linea) => strpos($linea, $ip) === false);
        file_put_contents($archivo, implode("\n", $lineas)."\n");
    }
    eliminarIP($ips_file, $ip_a_desbloquear);
    eliminarIP($ips_registro, $ip_a_desbloquear);
    header('Location: '.$_SERVER['PHP_SELF']);
    exit();
}

// Leer IPs bloqueadas permanentemente
$ips_permanentes = file_exists($ips_file) ? file($ips_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Leer todas las líneas del registro de bloqueos
$log_lines = file_exists($ips_registro) ? file($ips_registro, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Contar intentos por IP y registrar usuarios por IP
$intentos = [];
$usuarios_por_ip = [];
foreach($log_lines as $line){
    if(preg_match('/IP:\s*([\d\.]+)/', $line, $matches)){
        $ip_line = $matches[1];
        $intentos[$ip_line] = ($intentos[$ip_line] ?? 0) + 1;

        if(preg_match('/Usuario:\s*(.+?)\s*\|/', $line, $m)) {
            $usuario_linea = trim($m[1]);
            if($usuario_linea !== 'ANONIMO') $usuarios_por_ip[$ip_line][$usuario_linea] = true;
        }
    }
}

// Unir todas las IPs a mostrar (permanentes + las que aparecen en el registro)
$all_ips = array_unique(array_merge($ips_permanentes, array_keys($intentos)));

// Preparar alertas y formato de usuarios por IP
$alertas = [];
foreach($all_ips as $ip){
    $alertas[$ip] = true;
    $usuarios_por_ip[$ip] = isset($usuarios_por_ip[$ip]) ? implode('<br>', array_keys($usuarios_por_ip[$ip])) : '-';
}

// Función para obtener país y código de bandera
function obtenerPais($ip) {
    $geo = @json_decode(file_get_contents("http://ip-api.com/json/$ip"));
    if ($geo && $geo->status === "success") return [$geo->country, strtolower($geo->countryCode)];
    return ['Desconocido',''];
}

// =====================
// VERSION Y AUTOR
$version_file = 'version.xk';
$version = 'v.1.0'; $autor = '';
if(file_exists($version_file)){
    $lines = file($version_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if(isset($lines[0])) $version = $lines[0];
    if(isset($lines[1])) $autor = $lines[1];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>ITVControl</title>
<link rel="icon" href="images/logo.webp">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" sizes="180x180" href="images/logo.webp">
<link rel="stylesheet" href="style.css">

<style>
body { margin:15px; font-family:Arial,sans-serif; }
.user-info{
    position:fixed; top:10px; right:15px; text-align:right; font-size:14px;
    background:rgba(255,255,255,0.6); padding:5px 10px; border-radius:8px;
}
.user-info strong{display:block;}
.user-info small{color:#4a90e2;font-weight:bold;}
h1 img{vertical-align:middle;}
table{width:100%; border-collapse:collapse; margin-top:10px;}
th, td{padding:8px; border:1px solid #ccc; text-align:center; vertical-align:middle;}
th{background:#004aad; color:#fff;}
tr:hover{background:#f1f1f1;}
.flag{width:24px;height:18px; vertical-align:middle; margin-left:5px;}
button{padding:5px 10px; cursor:pointer; border:none; background:#ff4c4c; color:#fff; border-radius:4px;}
button:hover{background:#ff0000;}
.alerta{color:red; font-weight:bold;}
@media (prefers-color-scheme: dark){
    body{background:#000; color:#ddd;}
    h1,label,p,th,td,strong{color:#ddd;}
    table{background:#111;}
    th{background:#222;color:#fff;}
    tr:hover{background:#222;}
    button{background:#cc3333;color:#fff;}
    .menu a img:not([alt="Logo"]){filter:invert(1) hue-rotate(180deg);}
    h1 img{filter:none;}
    .user-info{background:rgba(0,0,0,0.5);}
    .user-info small{color:#3399ff;}
}
  
.btn-volver{

    display:inline-block;
    padding:10px 18px;
    background:#004aad;
    color:white;
    text-decoration:none;
    border-radius:6px;
    font-weight:bold;

}

.btn-volver:hover{

    background:#003580;
    color:white;

}
</style>
</head>
<body>

<div class="user-info">

<strong>
<?=htmlspecialchars($usuario_nombre)?>
</strong>

<small>
<?=htmlspecialchars($_SESSION["usuario"]["rol"])?>
</small>

<div id="fecha-hora"></div>

</div>

<h1>
<img src="images/logo.webp" width="30">
Seguridad - IPs bloqueadas
</h1><hr style="border:1px solid #4a90e2; margin:10px 0 20px 0;">

<div style="margin-bottom:20px;">

<a href="index.php" 
class="btn-volver">

⬅ Volver

</a>

</div>

<script>
function actualizarFechaHora(){
    const d=new Date();
    document.getElementById('fecha-hora').innerText = d.toLocaleDateString('es-ES')+' '+d.toLocaleTimeString('es-ES');
}
actualizarFechaHora();
setInterval(actualizarFechaHora,1000);
</script>

<?php if(empty($alertas)): ?>
<h2 style="color:green; text-align:center;">No hay IPs bloqueadas.</h2>
<?php else: ?>
<table>
    <tr>
    <th>⚠ Usuarios</th>
    <th>IP</th>
    <th>País</th>
    <th>Intentos</th>
    <?php if($is_superadmin): ?><th>Acción</th><?php endif; ?>
</tr>
<?php foreach($alertas as $ip=>$val):
    list($pais,$codigoPais)=obtenerPais($ip);
    $num_intentos = $intentos[$ip] ?? 0;
    $usuarios_html = $usuarios_por_ip[$ip] ?? '-';
    $mostrar_boton = in_array($ip,$ips_permanentes);
?>
<tr>
    <td class="alerta"><?= $usuarios_html ?></td>
    <td><?= htmlspecialchars($ip) ?></td>
    <td><?= htmlspecialchars($pais) ?> <?php if($codigoPais): ?><img src="https://flagcdn.com/24x18/<?= $codigoPais ?>.png" class="flag"><?php endif; ?></td>
    <td><?= $num_intentos ?></td>
    <?php if($is_superadmin): ?>
    <td>
        <?php if($mostrar_boton): ?>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="desbloquear_ip" value="<?= htmlspecialchars($ip) ?>">
            <button type="submit">Desbloquear</button>
        </form>
        <?php endif; ?>
    </td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h4 class="small" style="text-align:left; margin:4px 0;"><?= htmlspecialchars($version) ?></h4>
<p class="small" style="text-align:left; margin:0;"><?= htmlspecialchars($autor) ?></p>

</body>
</html>