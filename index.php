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
            $u["usuario"]==$usuario &&
            password_verify($password,$u["password"]) &&
            $u["activo"]
        ){

            $_SESSION["usuario"]=$u;

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
 GUARDAR PRODUCTO
****************************************************/

if(isset($_POST["guardar_producto"])){

    $productos = leerJSON("productos");

    $productos[] = [

        "id" => siguienteIdProducto(),

        "codigo" => siguienteCodigoProducto(),

        "nombre" => trim($_POST["nombre"]),

        "categoria" => trim($_POST["categoria"]),

        "proveedor" => trim($_POST["proveedor"]),

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

</style>

</head>

<body>

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

<input
name="usuario"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Contraseña</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<button
name="login"
class="btn btn-primary w-100">

Entrar

</button>

</form>

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
overflow:auto;

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

position:absolute;

bottom:15px;

left:15px;

color:#94a3b8;

font-size:12px;

line-height:1.4;

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

<h3><i class="bi bi-box-seam"></i> Almacén</h3>

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

$totalProductos=count($productos);

$totalStock=0;

$sinStock=0;

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

echo "<td>".$p["categoria"]."</td>";

echo "<td>".$p["stock"]."</td>";

echo "<td>".$p["stock_minimo"]."</td>";

echo "<td>".$p["ubicacion"]."</td>";

echo "</tr>";

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

<input
name="categoria"
class="form-control">

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

echo "<h2>Entradas</h2>";
echo "<div class='alert alert-warning'>Pendiente.</div>";

break;

/************************************************************/

case "salidas":

echo "<h2>Salidas</h2>";
echo "<div class='alert alert-warning'>Pendiente.</div>";

break;

/************************************************************/

case "historial":

echo "<h2>Historial</h2>";
echo "<div class='alert alert-warning'>Pendiente.</div>";

break;

/************************************************************/

case "categorias":

echo "<h2>Categorías</h2>";
echo "<div class='alert alert-warning'>Pendiente.</div>";

break;

/************************************************************/

case "proveedores":

echo "<h2>Proveedores</h2>";
echo "<div class='alert alert-warning'>Pendiente.</div>";

break;


/************************************************************
 USUARIOS
************************************************************/

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
<?= $u["usuario"] ?>
</td>

<td>
<?= $u["nombre"] ?>
</td>

<td>
<?= $u["rol"] ?>
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