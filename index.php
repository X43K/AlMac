<?php
/**********************************************************************
 * GESTIÓN DE ALMACÉN
 * Versión 1.0
 * Todo en un único archivo PHP
 **********************************************************************/

error_reporting(E_ALL);
ini_set("display_errors",1);
date_default_timezone_set("Europe/Madrid");
session_start();

/**********************************************************************
 CONFIGURACIÓN
**********************************************************************/

define("DATA_DIR",__DIR__."/data");

if(!is_dir(DATA_DIR))
    mkdir(DATA_DIR,0777,true);

$archivos=[
    "productos",
    "movimientos",
    "categorias",
    "proveedores",
    "usuarios"
];

foreach($archivos as $a){

    $f = DATA_DIR."/$a.json";

    if(!file_exists($f)){

        if($a=="usuarios"){

            $admin=[

                [
                    "id"=>1,
                    "usuario"=>"admin",
                    "password"=>password_hash("admin",PASSWORD_DEFAULT),
                    "nombre"=>"Administrador",
                    "rol"=>"Administrador",
                    "activo"=>1
                ]

            ];

            file_put_contents(
                $f,
                json_encode($admin,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)
            );

        }else{

            file_put_contents($f,"[]");

        }

    }

}

/**********************************************************************
 FUNCIONES JSON
**********************************************************************/

function leerJSON($archivo){

    $ruta=DATA_DIR."/$archivo.json";

    if(!file_exists($ruta))
        return [];

    $json=file_get_contents($ruta);

    $datos=json_decode($json,true);

    if(!$datos)
        $datos=[];

    return $datos;

}

function guardarJSON($archivo,$datos){

    file_put_contents(
        DATA_DIR."/$archivo.json",
        json_encode(
            $datos,
            JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE
        )
    );

}

/**********************************************************************
 INFORMACIÓN APLICACIÓN
**********************************************************************/

function leerVersion(){

    $archivo=__DIR__."/version.xk";

    if(file_exists($archivo)){

        return nl2br(
            htmlspecialchars(
                file_get_contents($archivo)
            )
        );

    }

    return "";

}

/**********************************************************************
 FUNCIONES PRODUCTOS
**********************************************************************/

function siguienteIdProducto(){

    $productos = leerJSON("productos");

    $max = 0;

    foreach($productos as $p){

        if(isset($p["id"]) && $p["id"] > $max)
            $max = $p["id"];

    }

    return $max + 1;

}

function siguienteCodigoProducto(){

    $productos = leerJSON("productos");

    $max = 0;

    foreach($productos as $p){

        if(isset($p["codigo"])){

            $num = intval(substr($p["codigo"],1));

            if($num > $max)
                $max = $num;

        }

    }

    return "P".str_pad($max+1,5,"0",STR_PAD_LEFT);

}

function actualizarProducto($datos){

    $productos = leerJSON("productos");

    foreach($productos as &$p){

        if($p["id"] == intval($datos["id"])){

            $p["nombre"] = trim($datos["nombre"]);
            $p["categoria"] = intval($datos["categoria"]);
            $p["proveedor"] = intval($datos["proveedor"]);
            $p["ubicacion"] = trim($datos["ubicacion"]);
            $p["precio_compra"] = floatval($datos["precio_compra"]);
            $p["precio_venta"] = floatval($datos["precio_venta"]);
            $p["stock"] = intval($datos["stock"]);
            $p["stock_minimo"] = intval($datos["stock_minimo"]);

            break;

        }

    }

    guardarJSON("productos",$productos);

}
/**********************************************************************
 USUARIOS
**********************************************************************/

function esAdministrador(){

    if(!isset($_SESSION["usuario"]))
        return false;

    return $_SESSION["usuario"]["rol"]=="Administrador";

}

function usuarios(){

    return leerJSON("usuarios");

}

function login($usuario,$password){

    foreach(usuarios() as $u){

        if(
            strtolower($u["usuario"]) == strtolower($usuario) &&
            password_verify($password,$u["password"]) &&
            $u["activo"]
        ){

            $_SESSION["usuario"] = $u;

            return true;

        }

    }

    return false;

}

function estaLogueado(){

    return isset($_SESSION["usuario"]);

}

function cerrarSesion(){

    $_SESSION = [];

    session_destroy();

    header("Location:?");

    exit;

}

/**********************************************************************
 GESTION USUARIOS
**********************************************************************/

function siguienteIdUsuario(){

    $usuarios=leerJSON("usuarios");

    $max=0;

    foreach($usuarios as $u){

        if($u["id"]>$max)
            $max=$u["id"];

    }

    return $max+1;

}


function crearUsuario($datos){

    $usuarios=leerJSON("usuarios");


    $usuarios[]=[

        "id"=>siguienteIdUsuario(),

        "usuario"=>$datos["usuario"],

        "password"=>password_hash(
            $datos["password"],
            PASSWORD_DEFAULT
        ),

        "nombre"=>$datos["nombre"],

        "rol"=>$datos["rol"],

        "activo"=>1

    ];


    guardarJSON("usuarios",$usuarios);

}



function eliminarUsuario($id){

    $usuarios=leerJSON("usuarios");


    $usuarios=array_filter(
        $usuarios,
        function($u) use ($id){

            return $u["id"]!=$id;

        }
    );


    guardarJSON(
        "usuarios",
        array_values($usuarios)
    );

}



function cambiarPasswordUsuario($id,$password){

    $usuarios=leerJSON("usuarios");


    foreach($usuarios as &$u){

        if($u["id"]==$id){

            $u["password"]=password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        }

    }


    guardarJSON("usuarios",$usuarios);

}

/**********************************************************************
 MOVIMIENTOS
**********************************************************************/

function siguienteIdMovimiento(){

    $movimientos=leerJSON("movimientos");

    $max=0;

    foreach($movimientos as $m){

        if(($m["id"] ?? 0)>$max)
            $max=$m["id"];

    }

    return $max+1;

}



function registrarMovimiento($tipo,$producto_id,$cantidad,$observaciones=""){

    $productos=leerJSON("productos");

    $movimientos=leerJSON("movimientos");

    foreach($productos as &$p){

        if($p["id"]==$producto_id){

if($tipo=="Entrada"){

    $p["stock"] += $cantidad;

}

if($tipo=="Salida"){

    if($p["stock"] < $cantidad){

        return "No hay suficiente stock.";

    }

    $p["stock"] -= $cantidad;

}

            $movimientos[]=[

                "id"=>siguienteIdMovimiento(),

                "fecha"=>date("Y-m-d H:i:s"),

                "tipo"=>$tipo,

                "producto_id"=>$p["id"],

                "producto"=>$p["nombre"],

                "cantidad"=>$cantidad,

                "usuario"=>$_SESSION["usuario"]["usuario"],

                "observaciones"=>$observaciones

            ];

            break;

        }

    }

    guardarJSON("productos",$productos);

    guardarJSON("movimientos",$movimientos);
return true;
}

function eliminarMovimiento($id){

    $movimientos = leerJSON("movimientos");
    $productos = leerJSON("productos");

    foreach($movimientos as $k=>$m){

        if($m["id"]==$id){

            foreach($productos as &$p){

                if($p["id"]==$m["producto_id"]){

                    if($m["tipo"]=="Entrada"){

                        $p["stock"] -= $m["cantidad"];

                    }

                    if($m["tipo"]=="Salida"){

                        $p["stock"] += $m["cantidad"];

                    }

                    break;

                }

            }

            unset($movimientos[$k]);

            guardarJSON("productos",$productos);

            guardarJSON(
                "movimientos",
                array_values($movimientos)
            );

            return true;

        }

    }

    return false;

}

/**********************************************************************
 CATEGORÍAS
**********************************************************************/

function siguienteIdCategoria(){

    $categorias = leerJSON("categorias");

    $max = 0;

    foreach($categorias as $c){

        if(($c["id"] ?? 0) > $max)
            $max = $c["id"];

    }

    return $max + 1;

}

function crearCategoria($nombre){

    $nombre = trim($nombre);

    if($nombre=="")
        return;

    $categorias = leerJSON("categorias");

    foreach($categorias as $c){

        if(strtolower($c["nombre"])==strtolower($nombre))
            return;

    }

    $categorias[]=[

        "id"=>siguienteIdCategoria(),

        "nombre"=>$nombre

    ];

    guardarJSON("categorias",$categorias);

}

function eliminarCategoria($id){

    $categorias = leerJSON("categorias");

    $productos = leerJSON("productos");

    foreach($categorias as $c){

        if($c["id"]==$id){

            foreach($productos as $p){

                if($p["categoria"]==$c["nombre"])
                    return false;

            }

        }

    }

    $categorias=array_values(array_filter(
        $categorias,
        fn($c)=>$c["id"]!=$id
    ));

    guardarJSON("categorias",$categorias);

    return true;

}

/**********************************************************************
 PROVEEDORES
**********************************************************************/

function siguienteIdProveedor(){

    $proveedores = leerJSON("proveedores");

    $max = 0;

    foreach($proveedores as $p){

        if(($p["id"] ?? 0) > $max)
            $max = $p["id"];

    }

    return $max + 1;

}

function crearProveedor($datos){

    $nombre = trim($datos["nombre"]);

    if($nombre=="")
        return;

    $proveedores = leerJSON("proveedores");

    foreach($proveedores as $p){

        if(strtolower($p["nombre"])==strtolower($nombre))
            return;

    }

    $proveedores[]=[

        "id"=>siguienteIdProveedor(),

        "nombre"=>$nombre,

        "contacto"=>trim($datos["contacto"]),

        "telefono"=>trim($datos["telefono"]),

        "email"=>trim($datos["email"]),

        "direccion"=>trim($datos["direccion"]),

        "observaciones"=>trim($datos["observaciones"])

    ];

    guardarJSON("proveedores",$proveedores);

}

function eliminarProveedor($id){

    $proveedores = leerJSON("proveedores");

    $productos = leerJSON("productos");

    foreach($proveedores as $p){

        if($p["id"]==$id){

            foreach($productos as $prod){

                if($prod["proveedor"]==$p["nombre"])
                    return false;

            }

        }

    }

    $proveedores=array_values(array_filter(

        $proveedores,

        fn($p)=>$p["id"]!=$id

    ));

    guardarJSON("proveedores",$proveedores);

    return true;

}

/***************************************************
 PROVEEDORES
****************************************************/

if(isset($_POST["crear_proveedor"])){

    crearProveedor($_POST);

    header("Location:?accion=proveedores");

    exit;

}

if(isset($_GET["borrar_proveedor"])){

    eliminarProveedor(intval($_GET["borrar_proveedor"]));

    header("Location:?accion=proveedores");

    exit;

}

/**********************************************************************
 ROUTER
**********************************************************************/

$accion = $_GET["accion"] ?? "dashboard";

if(isset($_POST["login"])){

    if(login($_POST["usuario"],$_POST["password"])){

        header("Location:?");

        exit;

    }

    $errorLogin="Usuario o contraseña incorrectos";

}

if($accion=="logout"){

    cerrarSesion();

}

/***************************************************
 GESTION DE USUARIOS
****************************************************/


if(isset($_POST["crear_usuario"])){

    if(esAdministrador()){

        crearUsuario($_POST);

    }

    header("Location:?accion=usuarios");

    exit;

}



if(isset($_GET["borrar_usuario"])){

    if(esAdministrador()){

        eliminarUsuario(
            intval($_GET["borrar_usuario"])
        );

    }

    header("Location:?accion=usuarios");

    exit;

}



if(isset($_POST["cambiar_password"])){

    $id=intval($_POST["id"]);

    $puede=false;


    if(esAdministrador()){

        $puede=true;

    }


    if(
        isset($_SESSION["usuario"])
        &&
        $_SESSION["usuario"]["id"]==$id
    ){

        $puede=true;

    }


    if($puede){

        cambiarPasswordUsuario(
            $id,
            $_POST["password"]
        );

    }


    header("Location:?accion=usuarios");

    exit;

}

/***************************************************
 CATEGORÍAS
****************************************************/

if(isset($_POST["crear_categoria"])){

    crearCategoria($_POST["nombre"]);

    header("Location:?accion=categorias");

    exit;

}

if(isset($_GET["borrar_categoria"])){

    eliminarCategoria(intval($_GET["borrar_categoria"]));

    header("Location:?accion=categorias");

    exit;

}

/***************************************************
 GUARDAR PRODUCTO
****************************************************/

if(isset($_POST["guardar_producto"])){

    $productos = leerJSON("productos");

    $productos[] = [

        "id" => siguienteIdProducto(),

        "codigo" => siguienteCodigoProducto(),

        "nombre" => trim($_POST["nombre"]),

        "categoria" => intval($_POST["categoria"]),

        "proveedor" => intval($_POST["proveedor"]),

        "ubicacion" => trim($_POST["ubicacion"]),

        "precio_compra" => floatval($_POST["precio_compra"]),

        "precio_venta" => floatval($_POST["precio_venta"]),

        "stock" => intval($_POST["stock"]),

        "stock_minimo" => intval($_POST["stock_minimo"])

    ];

    guardarJSON("productos",$productos);

    header("Location:?accion=productos");

    exit;

}

if(isset($_POST["editar_producto"])){

    if(esAdministrador()){

        actualizarProducto($_POST);

    }

    header("Location:?accion=productos");
    exit;

}

/***************************************************
 GUARDAR ENTRADA
****************************************************/

if(isset($_POST["guardar_entrada"])){

    registrarMovimiento(

        "Entrada",

        intval($_POST["producto"]),

        intval($_POST["cantidad"]),

        trim($_POST["observaciones"])

    );

    header("Location:?accion=entradas");

    exit;

}

/***************************************************
 GUARDAR SALIDA
****************************************************/

if(isset($_POST["guardar_salida"])){

    $resultado = registrarMovimiento(

        "Salida",

        intval($_POST["producto"]),

        intval($_POST["cantidad"]),

        trim($_POST["observaciones"])

    );

    if($resultado===true){

        header("Location:?accion=salidas");

    }else{

        header("Location:?accion=salidas&error=".urlencode($resultado));

    }

    exit;

}

/***************************************************
 ELIMINAR MOVIMIENTO
****************************************************/

if(isset($_GET["borrar_movimiento"])){

    if(esAdministrador()){

        eliminarMovimiento(
            intval($_GET["borrar_movimiento"])
        );

    }

    header("Location:?accion=historial");

    exit;

}

/**********************************************************************
 HTML
**********************************************************************/
?>
<?php

if(!estaLogueado()){

?>
<!doctype html>

<html lang="es">

<head>

<meta charset="utf-8">

<title>Login</title>

<link rel="icon" href="images/logo.webp">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" sizes="180x180" href="images/logo.webp">
<link rel="stylesheet" href="style.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#0f172a;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.card{

width:400px;

border-radius:15px;

}
.login-container{
    display:flex;
    flex-direction:column;
    align-items:center;
}

/* ===== CUADRO DE COOKIES ===== */
.cookies-box {
    margin-top: 25px;
    padding: 12px;
    border: 1px solid #ccc;
    background: #f7f7f7;
    color: #333;
    width: 330px;
    font-size: 13px;
    border-radius: 6px;
}
@media (prefers-color-scheme: dark) {
    .cookies-box {
        border: 1px solid #555;
        background: #1a1a1a;
        color: #ccc;
    }
}

</style>

</head>

<body>

<div class="login-container">

    <div class="card shadow">
        <div class="card-body">

            <h3 class="mb-4 text-center">
                Gestión de Almacén
            </h3>

            <?php
            if(isset($errorLogin))
                echo "<div class='alert alert-danger'>$errorLogin</div>";
            ?>

            <form method="post">

                <div class="mb-3">
                    <label>Usuario</label>
                    <input name="usuario" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button name="login" class="btn btn-primary w-100">
                    Entrar
                </button>

            </form>

        </div>
    </div>

    <div class="cookies-box">
        Esta web utiliza únicamente cookies técnicas necesarias para el inicio de sesión.
        No se emplean cookies de análisis, publicidad ni de terceros.
    </div>

</div>

</body>

</html>

<?php

exit;

}

?>
<!doctype html>
<html lang="es">

<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Gestión de Almacén</title>

<link rel="icon" href="images/logo.webp">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" sizes="180x180" href="images/logo.webp">
<link rel="stylesheet" href="style.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">

<style>

body{
background:#f5f5f5;
}

.sidebar{
    width:240px;
    height:100vh;
    background:#1e293b;
    position:fixed;
    left:0;
    top:0;
    overflow-y:auto;

    display:flex;
    flex-direction:column;
}

.sidebar h3{

color:white;
padding:20px;

}

.sidebar a{

display:block;
padding:12px 20px;
color:#ddd;
text-decoration:none;

}

.sidebar a:hover{

background:#334155;
color:white;

}

.version-app{
    margin-top:auto;
    padding:15px;
    color:#94a3b8;
    font-size:12px;
    line-height:1.4;
}

.sidebar hr{
    margin-bottom:0;
}

.main{

margin-left:240px;
padding:25px;

}

.card{

border:none;
border-radius:12px;

}

.valor{

font-size:30px;
font-weight:bold;

}

</style>

</head>

<body>

<div class="sidebar">

<h3><i class="bi bi-box-seam"></i> AlMac</h3>

  <div class="text-center text-white mb-4">

<i class="bi bi-person-circle fs-1"></i>

<br>

<?= $_SESSION["usuario"]["nombre"] ?>

<br>

<small>

<?= $_SESSION["usuario"]["rol"] ?>

</small>

</div>
  
<a href="?">
<i class="bi bi-speedometer2"></i>
 Dashboard
</a>

<a href="?accion=productos">
<i class="bi bi-box"></i>
 Productos
</a>

<a href="?accion=entradas">
<i class="bi bi-arrow-down-circle"></i>
 Entradas
</a>

<a href="?accion=salidas">
<i class="bi bi-arrow-up-circle"></i>
 Salidas
</a>

<a href="?accion=historial">
<i class="bi bi-clock-history"></i>
 Historial
</a>

<a href="?accion=categorias">
<i class="bi bi-tags"></i>
 Categorías
</a>

<a href="?accion=proveedores">
    <i class="bi bi-truck"></i>
    Proveedores
</a>

  <?php if(esAdministrador()){ ?>

<a href="?accion=usuarios">

<i class="bi bi-people"></i>

Usuarios

</a>

<?php } ?>
  
<hr class="text-secondary">

  <a href="?accion=perfil">
    <i class="bi bi-person-gear"></i>
    Mi perfil
</a>
  
<a href="?accion=logout">
    <i class="bi bi-box-arrow-right"></i>
    Cerrar sesión
</a>


<div class="version-app">

<?= leerVersion() ?>

</div>


</div>
<div class="main">

<?php

switch($accion){

/*************************************************************
 DASHBOARD
*************************************************************/

default:

$productos=leerJSON("productos");

$movimientos=leerJSON("movimientos");

$totalProductos = count($productos);

$totalStock = 0;

$sinStock = 0;

$stockCritico = [];

$stockBajo = [];

$stockCorrecto = [];

foreach($productos as $p){

    $stock = $p["stock"] ?? 0;
    $min = $p["stock_minimo"] ?? 0;

    $totalStock += $stock;

    if($stock <= 0){

        $sinStock++;
        $stockCritico[] = $p;

    }
    elseif($stock <= $min){

        $stockBajo[] = $p;

    }
    else{

        $stockCorrecto[] = $p;

    }

}

foreach($productos as $p){

    $totalStock+=$p["stock"] ?? 0;

    if(($p["stock"] ?? 0)==0)
        $sinStock++;

}

?>

<h2 class="mb-4">

Dashboard

</h2>

<div class="row">

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<div class="text-secondary">

Productos

</div>

<div class="valor">

<?= $totalProductos ?>

</div>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<div class="text-secondary">

Stock total

</div>

<div class="valor">

<?= $totalStock ?>

</div>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card shadow">

<div class="card-body">

<div class="text-secondary">

Sin stock

</div>

<div class="valor text-danger">

<?= $sinStock ?>

</div>

</div>

</div>

</div>

</div>

<hr>

<div class="row">

<div class="col-lg-4 mb-3">

<div class="card border-danger shadow">

<div class="card-header bg-danger text-white">

<i class="bi bi-exclamation-octagon-fill"></i>

Stock crítico

</div>

<div class="card-body">

<?php

if(count($stockCritico)==0){

    echo "<div class='alert alert-success mb-0'>
    No hay productos sin stock.
    </div>";

}else{

    foreach($stockCritico as $p){

        echo "<div class='d-flex justify-content-between border-bottom py-2'>";

        echo "<strong>".$p["nombre"]."</strong>";

        echo "<span class='badge bg-danger'>".$p["stock"]." / ".$p["stock_minimo"]."</span>";

        echo "</div>";

    }

}

?>

</div>

</div>

</div>

<div class="col-lg-4 mb-3">

<div class="card border-warning shadow">

<div class="card-header bg-warning">

<i class="bi bi-exclamation-triangle-fill"></i>

Stock bajo

</div>

<div class="card-body">

<?php

if(count($stockBajo)==0){

    echo "<div class='alert alert-success mb-0'>
    Todos los productos están por encima del mínimo.
    </div>";

}else{

    foreach($stockBajo as $p){

        echo "<div class='d-flex justify-content-between border-bottom py-2'>";

        echo "<strong>".$p["nombre"]."</strong>";

        echo "<span class='badge bg-warning text-dark'>".$p["stock"]." / ".$p["stock_minimo"]."</span>";

        echo "</div>";

    }

}

?>

</div>

</div>

</div>

<div class="col-lg-4 mb-3">

<div class="card border-success shadow">

<div class="card-header bg-success text-white">

<i class="bi bi-check-circle-fill"></i>

Resto almacén

</div>

<div class="card-body">

<?php

if(count($stockCorrecto)==0){

    echo "<div class='alert alert-warning mb-0'>
    No hay productos con stock correcto.
    </div>";

}else{

    foreach($stockCorrecto as $p){

        echo "<div class='d-flex justify-content-between border-bottom py-2'>";

        echo "<strong>".$p["nombre"]."</strong>";

        echo "<span class='badge bg-success'>".$p["stock"]." / ".$p["stock_minimo"]."</span>";

        echo "</div>";

    }

}

?>

</div>

</div>

</div>

</div>
  
<hr>

<h4>

Últimos movimientos

</h4>

<table class="table table-striped bg-white shadow">

<thead>

<tr>

<th>Fecha</th>

<th>Tipo</th>

<th>Producto</th>

<th>Cantidad</th>

</tr>

</thead>

<tbody>

<?php

$ultimos=array_reverse($movimientos);

$ultimos=array_slice($ultimos,0,10);

foreach($ultimos as $m){

echo "<tr>";

echo "<td>".$m["fecha"]."</td>";

echo "<td>".$m["tipo"]."</td>";

echo "<td>".$m["producto"]."</td>";

echo "<td>".$m["cantidad"]."</td>";

echo "</tr>";

}

?>

</tbody>

</table>
  
<?php

break;

/*************************************************************
 PRODUCTOS
*************************************************************/

case "productos":

$productos = leerJSON("productos");
?>

<div class="d-flex justify-content-between mb-3">

<h2>Productos</h2>

<button
class="btn btn-primary"
data-bs-toggle="modal"
data-bs-target="#nuevoProducto">

<i class="bi bi-plus-circle"></i>

Nuevo producto

</button>

</div>

<input
id="buscar"
class="form-control mb-3"
placeholder="Buscar producto...">

<table
id="tablaProductos"
class="table table-striped table-hover bg-white shadow">

<thead>

<tr>

<th>Código</th>

<th>Nombre</th>

<th>Categoría</th>

<th>Stock</th>

<th>Mínimo</th>

<th>Ubicación</th>

<?php if(esAdministrador()){ ?>

<th>Acciones</th>

<?php } ?>

</tr>

</thead>

<tbody>

<?php

foreach($productos as $p){

$color="";

if($p["stock"]<=0)
    $color="table-danger";
elseif($p["stock"]<=$p["stock_minimo"])
    $color="table-warning";

echo "<tr class='$color'>";

echo "<td>".$p["codigo"]."</td>";

echo "<td>".$p["nombre"]."</td>";

$nombreCategoria = "Sin categoría";

foreach(leerJSON("categorias") as $c){

    if($c["id"] == $p["categoria"]){

        $nombreCategoria = $c["nombre"];
        break;

    }

}

echo "<td>".htmlspecialchars($nombreCategoria)."</td>";
echo "<td>".$p["stock"]."</td>";

echo "<td>".$p["stock_minimo"]."</td>";

echo "<td>".$p["ubicacion"]."</td>";

if(esAdministrador()){

    echo "<td>";

    echo "<button class='btn btn-warning btn-sm'
            data-bs-toggle='modal'
            data-bs-target='#editar".$p["id"]."'>";

    echo "<i class='bi bi-pencil'></i>";

    echo "</button>";

    echo "</td>";

}

echo "</tr>";

?>

<?php if(esAdministrador()){ ?>

<div class="modal fade" id="editar<?=$p["id"]?>">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form method="post">

<input type="hidden" name="id" value="<?=$p["id"]?>">

<div class="modal-header">

<h5>Editar producto</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<div class="row">

<div class="col-md-6 mb-3">

<label>Nombre</label>

<input
name="nombre"
class="form-control"
value="<?=htmlspecialchars($p["nombre"])?>"
required>

</div>

<div class="col-md-6 mb-3">

<label>Categoría</label>

<select
name="categoria"
class="form-select">

<?php foreach(leerJSON("categorias") as $c){ ?>

<option
value="<?=$c["id"]?>"
<?=$c["id"]==$p["categoria"]?"selected":""?>>

<?=htmlspecialchars($c["nombre"])?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label>Proveedor</label>

<input
name="proveedor"
class="form-control"
value="<?=$p["proveedor"]?>">

</div>

<div class="col-md-6 mb-3">

<label>Ubicación</label>

<input
name="ubicacion"
class="form-control"
value="<?=htmlspecialchars($p["ubicacion"])?>">

</div>

<div class="col-md-3">

<label>Compra</label>

<input
type="number"
step="0.01"
name="precio_compra"
class="form-control"
value="<?=$p["precio_compra"]?>">

</div>

<div class="col-md-3">

<label>Venta</label>

<input
type="number"
step="0.01"
name="precio_venta"
class="form-control"
value="<?=$p["precio_venta"]?>">

</div>

<div class="col-md-3">

<label>Stock</label>

<input
type="number"
name="stock"
class="form-control"
value="<?=$p["stock"]?>">

</div>

<div class="col-md-3">

<label>Stock mínimo</label>

<input
type="number"
name="stock_minimo"
class="form-control"
value="<?=$p["stock_minimo"]?>">

</div>

</div>

</div>

<div class="modal-footer">

<button
name="editar_producto"
class="btn btn-success">

Guardar cambios

</button>

</div>

</form>

</div>

</div>

</div>

<?php } ?>

<?php

}

?>

</tbody>

</table>

<!-- Modal -->

<div class="modal fade" id="nuevoProducto">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form method="post">

<div class="modal-header">

<h5>Nuevo producto</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<div class="row">

<div class="col-md-6 mb-3">

<label>Nombre</label>

<input
required
name="nombre"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Categoría</label>

<select
name="categoria"
class="form-select">

<option value="0">

Sin categoría

</option>

<?php

foreach(leerJSON("categorias") as $c){

?>

<option value="<?=$c["id"]?>">

<?=htmlspecialchars($c["nombre"])?>

</option>

<?php

}

?>

</select>

</div>

<div class="col-md-6 mb-3">

<label>Proveedor</label>

<input
name="proveedor"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Ubicación</label>

<input
name="ubicacion"
class="form-control">

</div>

<div class="col-md-3">

<label>Compra</label>

<input
type="number"
step="0.01"
name="precio_compra"
class="form-control"
value="0">

</div>

<div class="col-md-3">

<label>Venta</label>

<input
type="number"
step="0.01"
name="precio_venta"
class="form-control"
value="0">

</div>

<div class="col-md-3">

<label>Stock</label>

<input
type="number"
name="stock"
class="form-control"
value="0">

</div>

<div class="col-md-3">

<label>Stock mínimo</label>

<input
type="number"
name="stock_minimo"
class="form-control"
value="0">

</div>

</div>

</div>

<div class="modal-footer">

<button
class="btn btn-success"
name="guardar_producto">

Guardar

</button>

</div>

</form>

</div>

</div>

</div>

<script>

document.getElementById("buscar").addEventListener("keyup",function(){

let filtro=this.value.toLowerCase();

document.querySelectorAll("#tablaProductos tbody tr").forEach(function(fila){

fila.style.display=fila.innerText.toLowerCase().includes(filtro)
?"":"none";

});

});

</script>

<?php

break;

/************************************************************/

case "entradas":

$productos=leerJSON("productos");

$movimientos=leerJSON("movimientos");

?>

<h2 class="mb-4">

Entradas de mercancía

</h2>

<form method="post" class="card shadow p-4 mb-4">

<div class="row">

<div class="col-md-5">

<label>Producto</label>

<select
name="producto"
class="form-select"
required>

<option value="">Seleccione...</option>

<?php

foreach($productos as $p){

echo "<option value='".$p["id"]."'>";

echo $p["codigo"]." - ".$p["nombre"];

echo "</option>";

}

?>

</select>

</div>

<div class="col-md-2">

<label>Cantidad</label>

<input
type="number"
name="cantidad"
class="form-control"
required
min="1">

</div>

<div class="col-md-5">

<label>Observaciones</label>

<input
name="observaciones"
class="form-control">

</div>

</div>

<div class="mt-3">

<button
name="guardar_entrada"
class="btn btn-success">

<i class="bi bi-box-arrow-in-down"></i>

Registrar entrada

</button>

</div>

</form>

<h4>

Últimas entradas

</h4>

<table class="table table-striped bg-white shadow">

<thead>

<tr>

<th>Fecha</th>

<th>Producto</th>

<th>Cantidad</th>

<th>Usuario</th>

</tr>

</thead>

<tbody>

<?php

$lista=array_reverse($movimientos);

foreach($lista as $m){

    if($m["tipo"]!="Entrada")
        continue;

    echo "<tr>";

    echo "<td>".$m["fecha"]."</td>";

    echo "<td>".$m["producto"]."</td>";

    echo "<td class='text-success fw-bold'>+".$m["cantidad"]."</td>";

    echo "<td>".$m["usuario"]."</td>";

    echo "</tr>";

}

?>

</tbody>

</table>

<?php

break;

/************************************************************/

case "salidas":

$productos = leerJSON("productos");

$movimientos = leerJSON("movimientos");

?>

<h2 class="mb-4">

Salidas de mercancía

</h2>

<?php

if(isset($_GET["error"])){

    echo "<div class='alert alert-danger'>";

    echo htmlspecialchars($_GET["error"]);

    echo "</div>";

}

?>

<form method="post" class="card shadow p-4 mb-4">

<div class="row">

<div class="col-md-5">

<label>Producto</label>

<select
name="producto"
class="form-select"
required>

<option value="">Seleccione...</option>

<?php

foreach($productos as $p){

    echo "<option value='".$p["id"]."'>";

    echo $p["codigo"];

    echo " - ";

    echo $p["nombre"];

    echo " (Stock: ".$p["stock"].")";

    echo "</option>";

}

?>

</select>

</div>

<div class="col-md-2">

<label>Cantidad</label>

<input
type="number"
name="cantidad"
class="form-control"
required
min="1">

</div>

<div class="col-md-5">

<label>Observaciones</label>

<input
name="observaciones"
class="form-control">

</div>

</div>

<div class="mt-3">

<button
name="guardar_salida"
class="btn btn-danger">

<i class="bi bi-box-arrow-up"></i>

Registrar salida

</button>

</div>

</form>

<h4>

Últimas salidas

</h4>

<table class="table table-striped bg-white shadow">

<thead>

<tr>

<th>Fecha</th>

<th>Producto</th>

<th>Cantidad</th>

<th>Usuario</th>

</tr>

</thead>

<tbody>

<?php

$lista = array_reverse($movimientos);

foreach($lista as $m){

    if($m["tipo"]!="Salida")
        continue;

    echo "<tr>";

    echo "<td>".$m["fecha"]."</td>";

    echo "<td>".$m["producto"]."</td>";

    echo "<td class='text-danger fw-bold'>-".$m["cantidad"]."</td>";

    echo "<td>".$m["usuario"]."</td>";

    echo "</tr>";

}

?>

</tbody>

</table>

<?php

break;

/************************************************************/

case "historial":

$movimientos = array_reverse(leerJSON("movimientos"));

$tipo = $_GET["tipo"] ?? "";

$buscar = trim($_GET["buscar"] ?? "");

?>

<h2 class="mb-4">

Historial de movimientos

</h2>

<form class="row g-3 mb-4">

<input
type="hidden"
name="accion"
value="historial">

<div class="col-md-3">

<label>Tipo</label>

<select
name="tipo"
class="form-select">

<option value="">Todos</option>

<option value="Entrada" <?=($tipo=="Entrada")?"selected":""?>>

Entradas

</option>

<option value="Salida" <?=($tipo=="Salida")?"selected":""?>>

Salidas

</option>

</select>

</div>

<div class="col-md-5">

<label>Producto</label>

<input
type="text"
name="buscar"
class="form-control"
value="<?=htmlspecialchars($buscar)?>"
placeholder="Buscar producto...">

</div>

<div class="col-md-2 d-flex align-items-end">

<button class="btn btn-primary w-100">

<i class="bi bi-search"></i>

Filtrar

</button>

</div>

<div class="col-md-2 d-flex align-items-end">

<a
href="?accion=historial"
class="btn btn-secondary w-100">

Limpiar

</a>

</div>

</form>

<table class="table table-striped table-hover bg-white shadow">

<thead>

<tr>

<th>Fecha</th>

<th>Tipo</th>

<th>Producto</th>

<th>Cantidad</th>

<th>Usuario</th>

<th>Observaciones</th>

<?php if(esAdministrador()){ ?>

<th>Acciones</th>

<?php } ?>

</tr>

</thead>

<tbody>

<?php

foreach($movimientos as $m){

    if($tipo!="" && $m["tipo"]!=$tipo)
        continue;

    if(
        $buscar!=""
        &&
        stripos($m["producto"],$buscar)===false
    )
        continue;

    $color = ($m["tipo"]=="Entrada")
        ? "text-success"
        : "text-danger";

    $signo = ($m["tipo"]=="Entrada")
        ? "+"
        : "-";

    ?>

    <tr>

    <td><?=htmlspecialchars($m["fecha"])?></td>

    <td>

        <?php if($m["tipo"]=="Entrada"){ ?>

            <span class="badge bg-success">

            Entrada

            </span>

        <?php }else{ ?>

            <span class="badge bg-danger">

            Salida

            </span>

        <?php } ?>

    </td>

    <td><?=htmlspecialchars($m["producto"])?></td>

    <td class="<?=$color?> fw-bold">

        <?=$signo?><?=$m["cantidad"]?>

    </td>

    <td><?=htmlspecialchars($m["usuario"])?></td>

    <td><?=htmlspecialchars($m["observaciones"])?></td>

      <?php if(esAdministrador()){ ?>

<td>

<a
href="?accion=historial&borrar_movimiento=<?=$m["id"]?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Eliminar este movimiento?')">

<i class="bi bi-trash"></i>

</a>

</td>

<?php } ?>
      
    </tr>

    <?php

}

?>

</tbody>

</table>

<?php

break;

/************************************************************/

case "categorias":

$categorias = leerJSON("categorias");

?>

<div class="d-flex justify-content-between mb-3">

<h2>Categorías</h2>

<button
class="btn btn-primary"
data-bs-toggle="modal"
data-bs-target="#nuevaCategoria">

<i class="bi bi-plus-circle"></i>

Nueva categoría

</button>

</div>

<table class="table table-striped bg-white shadow">

<thead>

<tr>

<th>Nombre</th>

<th width="120">Acciones</th>

</tr>

</thead>

<tbody>

<?php

foreach($categorias as $c){

?>

<tr>

<td>

<?=htmlspecialchars($c["nombre"])?>

</td>

<td>

<a
href="?accion=categorias&borrar_categoria=<?=$c["id"]?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Eliminar categoría?')">

<i class="bi bi-trash"></i>

</a>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

<div class="modal fade" id="nuevaCategoria">

<div class="modal-dialog">

<div class="modal-content">

<form method="post">

<div class="modal-header">

<h5>Nueva categoría</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">

</button>

</div>

<div class="modal-body">

<label>Nombre</label>

<input
name="nombre"
class="form-control"
required>

</div>

<div class="modal-footer">

<button
class="btn btn-success"
name="crear_categoria">

Guardar

</button>

</div>

</form>

</div>

</div>

</div>

<?php

break;

/************************************************************/

case "proveedores":

$proveedores = leerJSON("proveedores");

?>

<div class="d-flex justify-content-between mb-3">

<h2>Proveedores</h2>

<button
class="btn btn-primary"
data-bs-toggle="modal"
data-bs-target="#nuevoProveedor">

<i class="bi bi-plus-circle"></i>

Nuevo proveedor

</button>

</div>

<table class="table table-striped table-hover bg-white shadow">

<thead>

<tr>

<th>Empresa</th>

<th>Contacto</th>

<th>Teléfono</th>

<th>Email</th>

<th width="120">Acciones</th>

</tr>

</thead>

<tbody>

<?php foreach($proveedores as $p){ ?>

<tr>

<td><?=htmlspecialchars($p["nombre"])?></td>

<td><?=htmlspecialchars($p["contacto"])?></td>

<td><?=htmlspecialchars($p["telefono"])?></td>

<td><?=htmlspecialchars($p["email"])?></td>

<td>

<a
href="?accion=proveedores&borrar_proveedor=<?=$p["id"]?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Eliminar proveedor?')">

<i class="bi bi-trash"></i>

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<div class="modal fade" id="nuevoProveedor">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form method="post">

<div class="modal-header">

<h5>Nuevo proveedor</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<div class="row">

<div class="col-md-6 mb-3">

<label>Empresa</label>

<input
name="nombre"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label>Persona de contacto</label>

<input
name="contacto"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Teléfono</label>

<input
name="telefono"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Email</label>

<input
type="email"
name="email"
class="form-control">

</div>

<div class="col-12 mb-3">

<label>Dirección</label>

<textarea
name="direccion"
class="form-control"
rows="2"></textarea>

</div>

<div class="col-12">

<label>Observaciones</label>

<textarea
name="observaciones"
class="form-control"
rows="3"></textarea>

</div>

</div>

</div>

<div class="modal-footer">

<button
class="btn btn-success"
name="crear_proveedor">

Guardar proveedor

</button>

</div>

</form>

</div>

</div>

</div>

<?php

break;


/************************************************************
 USUARIOS
************************************************************/
case "perfil":

$u = $_SESSION["usuario"];

?>

<h2 class="mb-4">Mi perfil</h2>

<div class="card shadow" style="max-width:600px;">

<div class="card-body">

<div class="mb-3">
<label>Usuario</label>
<input class="form-control"
value="<?=htmlspecialchars($u["usuario"])?>"
disabled>
</div>

<div class="mb-3">
<label>Nombre</label>
<input class="form-control"
value="<?=htmlspecialchars($u["nombre"])?>"
disabled>
</div>

<hr>

<h5>Cambiar contraseña</h5>

<form method="post">

<input
type="hidden"
name="id"
value="<?=$u["id"]?>">

<div class="mb-3">

<label>Nueva contraseña</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<button
name="cambiar_password"
class="btn btn-success">

Guardar contraseña

</button>

</form>

</div>

</div>

<?php

break;
    
case "usuarios":

if(!esAdministrador()){

    echo "<div class='alert alert-danger'>
    No tienes permisos para acceder.
    </div>";

    break;

}


$usuarios=leerJSON("usuarios");

?>

<h2 class="mb-4">
Usuarios
</h2>

  <button
class="btn btn-primary mb-3"
data-bs-toggle="modal"
data-bs-target="#nuevoUsuario">

<i class="bi bi-person-plus"></i>

Nuevo usuario

</button>

<table class="table table-striped bg-white shadow">

<thead>

<tr>

<th>Usuario</th>

<th>Nombre</th>

<th>Rol</th>

<th>Acciones</th>

</tr>

</thead>


<tbody>

<?php foreach($usuarios as $u){ ?>

<tr>

<td>
<?= htmlspecialchars($u["usuario"]) ?>
</td>

<td>
<?= htmlspecialchars($u["nombre"]) ?>
</td>

<td>
<?= htmlspecialchars($u["rol"]) ?>
</td>

<td>

<button
class="btn btn-warning btn-sm"
data-bs-toggle="modal"
data-bs-target="#password<?=$u["id"]?>">

<i class="bi bi-key"></i>

Cambiar contraseña

</button>


<a 
href="?borrar_usuario=<?=$u["id"]?>&accion=usuarios"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Eliminar usuario?')">

<i class="bi bi-trash"></i>

</a>


</td>

</tr>

  <!-- MODAL CAMBIAR PASSWORD -->

<div class="modal fade" id="password<?=$u["id"]?>">

<div class="modal-dialog">

<div class="modal-content">


<form method="post">


<div class="modal-header">

<h5>

Cambiar contraseña de <?=$u["usuario"]?>

</h5>


<button
type="button"
class="btn-close"
data-bs-dismiss="modal">

</button>


</div>


<div class="modal-body">


<input
type="hidden"
name="id"
value="<?=$u["id"]?>">


<label>
Nueva contraseña
</label>


<input
type="password"
name="password"
class="form-control"
required>


</div>


<div class="modal-footer">


<button
class="btn btn-success"
name="cambiar_password">

Guardar contraseña

</button>


</div>


</form>


</div>

</div>

</div>

<?php } ?>

</tbody>

</table>


  <!-- MODAL NUEVO USUARIO -->

<div class="modal fade" id="nuevoUsuario">

<div class="modal-dialog modal-lg">


<div class="modal-content">


<form method="post">


<div class="modal-header">

<h5>

Nuevo usuario

</h5>


<button
type="button"
class="btn-close"
data-bs-dismiss="modal">

</button>


</div>



<div class="modal-body">


<div class="row">


<div class="col-md-6 mb-3">


<label>
Usuario
</label>


<input
name="usuario"
class="form-control"
required>


</div>



<div class="col-md-6 mb-3">


<label>
Nombre
</label>


<input
name="nombre"
class="form-control"
required>


</div>



<div class="col-md-6 mb-3">


<label>
Contraseña inicial
</label>


<input
type="password"
name="password"
class="form-control"
required>


</div>



<div class="col-md-6 mb-3">


<label>
Rol
</label>


<select
name="rol"
class="form-select">


<option>
Administrador
</option>


<option>
Operario
</option>


<option>
Lectura
</option>


</select>


</div>


</div>


</div>



<div class="modal-footer">


<button
class="btn btn-success"
name="crear_usuario">

Crear usuario

</button>


</div>


</form>


</div>


</div>


</div>
  
<?php

break;


}

?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>