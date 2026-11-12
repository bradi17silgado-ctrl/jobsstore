<?php
declare(strict_types=1);
// Entrada pública: reutiliza el controlador principal.
$_SERVER["REQUEST_URI"] = "/seguimiento" . (isset($_SERVER["QUERY_STRING"]) && $_SERVER["QUERY_STRING"] !== "" ? "?" . $_SERVER["QUERY_STRING"] : "");
require __DIR__ . "/index.php";
