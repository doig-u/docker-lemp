<?php
$servername = "localhost";
$username = "testuser";
$password = "testpassword";

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 

$conn->select_db('testdb') or die("Error: select_db: ". $conn->connect_error);

$dt = date("Y.m.dTH:i:s");
$conn->query("insert into counter values('$dt')") or die("Error: insert: ". $conn->connect_error);

$result = $conn->query("select count(*) as total from counter") or die("Error: select: ". $conn->connect_error);
$data=$result->fetch_assoc();
echo "Pageview # ".$data["total"];

?>
