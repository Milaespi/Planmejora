<?php
/**
 * auth.php — Inicio de sesión de usuarios
 *
 * Solo acepta peticiones POST.
 * Recibe: { "nombre": "Espinosa", "password": "12345" }
 * Retorna: los datos del usuario (sin la contraseña hasheada)
 *
 * La contraseña se verifica con bcrypt (password_verify) contra el hash
 * guardado en la base de datos — nunca se guarda la contraseña en texto plano.
 */
require_once __DIR__ . '/../config/database.php';

// Solo se permite el método POST; cualquier otro devuelve error 405
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

// Lee el cuerpo de la petición (viene en formato JSON) y lo convierte a arreglo PHP
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Valida que el frontend haya enviado los dos campos obligatorios
if (empty($body['nombre']) || empty($body['password'])) {
    jsonError('Usuario y contraseña son requeridos');
}

// Busca en la tabla "usuarios" un registro cuyo nombre coincida
// ilike = búsqueda sin distinguir mayúsculas/minúsculas (Espinosa = espinosa = ESPINOSA)
$usuarios = supabase('GET', 'usuarios', [
    'nombre' => 'ilike.' . trim($body['nombre']),
    'select' => '*',
]);

// Si no existe el usuario O si la contraseña no coincide con el hash → error de autenticación
// password_verify() compara la contraseña ingresada contra el hash bcrypt almacenado
if (empty($usuarios) || !password_verify($body['password'], $usuarios[0]['password_hash'] ?? '')) {
    jsonError('Usuario o contraseña incorrectos', 401);
}

// Prepara los datos del usuario para enviar al frontend
$usuario = $usuarios[0];
// Elimina el campo password_hash antes de enviarlo — nunca se devuelve al cliente
unset($usuario['password_hash']);

// Responde con los datos del usuario (id, nombre, rol, etc.)
jsonResponse($usuario);
