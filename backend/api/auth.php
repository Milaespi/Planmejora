<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (empty($body['nombre']) || empty($body['password'])) {
    jsonError('Usuario y contraseña son requeridos');
}

$usuarios = supabase('GET', 'usuarios', [
    'nombre' => 'ilike.' . trim($body['nombre']),
    'select' => '*',
]);

if (empty($usuarios) || !password_verify($body['password'], $usuarios[0]['password_hash'] ?? '')) {
    jsonError('Usuario o contraseña incorrectos', 401);
}

$usuario = $usuarios[0];
unset($usuario['password_hash']);
jsonResponse($usuario);
