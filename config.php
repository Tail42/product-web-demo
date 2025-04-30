
<?php
$host = "140.123.102.94";
$port = "3306";
$username = "410410098";
$password = "410410098";
$database = "410410098";

$conn = mysqli_connect($host, $username, $password, $database, $port);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>