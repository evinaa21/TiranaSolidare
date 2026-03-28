<?php
require 'c:\xampp\htdocs\TiranaSolidare\config\db.php';
$stmt = $pdo->query('SELECT * FROM Perdoruesi ORDER BY id_perdoruesi DESC LIMIT 3');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
