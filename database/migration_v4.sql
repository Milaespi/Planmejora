-- Migración v4: usuario único Espinosa
UPDATE usuarios SET
    nombre        = 'Espinosa',
    password_hash = '$2y$10$nQTC4KktbhWZnn/esJHuVO3i/J2kLaohYl6HCzgGWaTuSuPnuSWKW'
WHERE id = 1;

DELETE FROM usuarios WHERE id IN (2, 3);
