<?php
// Returns JSON for AJAX (event list)
header('Content-Type: application/json');
echo json_encode(["events" => []]);
