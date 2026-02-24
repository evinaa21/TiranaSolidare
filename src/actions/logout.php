<?php
session_start();
session_unset();
session_destroy();
header("Location: /TiranaSolidare/views/login.php");
exit();