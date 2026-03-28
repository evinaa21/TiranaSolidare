<?php
require 'c:\xampp\htdocs\TiranaSolidare\config\db.php';
require 'c:\xampp\htdocs\TiranaSolidare\api\helpers.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'reports';
$_SESSION['user_id'] = 2;
$_SESSION['roli'] = 'admin';
$_SESSION['_auth_verified_at'] = time();
ob_start();
include 'c:\xampp\htdocs\TiranaSolidare\api\stats.php';
$out = ob_get_clean();
echo substr($out, 0, 1000);
