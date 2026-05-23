<?php 
$server='localhost';
$bd = 'PNK_INMOBILIARIA';
$user='usuarioEmpresa';
$pass='usuarioEmpresa';

$conn =@mysqli_connect($server,$user,$pass,$bd);

if (!$conn) {
    $conn = null;
}

//echo "<div class='alert alert-success'>Conexión exitosa!</div>";

?>
