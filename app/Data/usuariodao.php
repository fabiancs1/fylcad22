<?php

/**
 * Componente de Acceso a Datos: UsuarioDAO
 * Responsabilidad: Ejecutar operaciones CRUD sobre la tabla `usuarios`.
 * Invoca el Singleton Database para obtener la conexión PDO activa.
 * Ubicación: /app/Data/UsuarioDAO.php
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Usuario.php';

class UsuarioDAO {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ════════════════════════════════════════════════════════════════
    // CREATE — Registrar un nuevo usuario
    // ════════════════════════════════════════════════════════════════

    /**
     * Inserta un nuevo usuario. La contraseña debe llegar ya hasheada (bcrypt).
     * @return int  ID generado, o 0 si falla.
     */
    public function crear(Usuario $u): int {
        $sql = "INSERT INTO usuarios
                    (nombre, email, password, plan, activo, avatar_color)
                VALUES
                    (:nombre, :email, :password, :plan, :activo, :avatar_color)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nombre'       => $u->getNombre(),
            ':email'        => $u->getEmail(),
            ':password'     => $u->getPassword(),
            ':plan'         => $u->getPlan(),
            ':activo'       => $u->getActivo(),
            ':avatar_color' => $u->getAvatarColor(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════════
    // READ — Consultar usuarios
    // ════════════════════════════════════════════════════════════════

    /**
     * Busca un usuario por su ID primario.
     * @return Usuario|null
     */
    public function obtenerPorId(int $id): ?Usuario {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Busca un usuario por email (usado en login y recuperación de contraseña).
     * @return Usuario|null
     */
    public function obtenerPorEmail(string $email): ?Usuario {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $fila = $stmt->fetch();
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Devuelve todos los usuarios activos del sistema.
     * @return Usuario[]
     */
    public function obtenerTodos(): array {
        $stmt = $this->pdo->query("SELECT * FROM usuarios WHERE activo = 1 ORDER BY creado_en DESC");
        return array_map([$this, 'hidratar'], $stmt->fetchAll());
    }

    // ════════════════════════════════════════════════════════════════
    // UPDATE — Modificar datos de un usuario
    // ════════════════════════════════════════════════════════════════

    /**
     * Actualiza el perfil editable del usuario (nombre, plan, color de avatar).
     * @return bool  true si se modificó al menos una fila.
     */
    public function actualizar(Usuario $u): bool {
        $sql = "UPDATE usuarios SET
                    nombre       = :nombre,
                    plan         = :plan,
                    activo       = :activo,
                    avatar_color = :avatar_color,
                    ultimo_acceso = :ultimo_acceso
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nombre'        => $u->getNombre(),
            ':plan'          => $u->getPlan(),
            ':activo'        => $u->getActivo(),
            ':avatar_color'  => $u->getAvatarColor(),
            ':ultimo_acceso' => $u->getUltimoAcceso(),
            ':id'            => $u->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // DELETE — Desactivar o eliminar usuario
    // ════════════════════════════════════════════════════════════════

    /**
     * Elimina físicamente un usuario. El CASCADE en FK elimina sus proyectos,
     * sesiones, cotizaciones y actividad asociada automáticamente.
     * @return bool
     */
    public function eliminar(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // HIDRATACIÓN — Fila SQL → objeto Usuario
    // ════════════════════════════════════════════════════════════════

    private function hidratar(array $fila): Usuario {
        $u = new Usuario();
        $u->setId((int)$fila['id']);
        $u->setNombre($fila['nombre']);
        $u->setEmail($fila['email']);
        $u->setPassword($fila['password']);
        $u->setPlan($fila['plan']);
        $u->setActivo((int)$fila['activo']);
        $u->setAvatarColor($fila['avatar_color']);
        $u->setUltimoAcceso($fila['ultimo_acceso']);
        $u->setCreadoEn($fila['creado_en']);
        $u->setActualizadoEn($fila['actualizado_en']);
        $u->setResetToken($fila['reset_token']);
        $u->setResetExpira($fila['reset_expira']);
        return $u;
    }
}