<?php

use GlpiPlugin\Quadros\Api;

include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    return;
}

echo json_encode(Api::handle($_POST), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
