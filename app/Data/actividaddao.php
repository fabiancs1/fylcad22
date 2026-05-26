<?php

/**
 * Componente de Acceso a Datos: ActividadDAO
 * Responsabilidad: Registrar y consultar la bitácora de auditoría del sistema.
 * Invoca el Singleton Database para obtener la conexión PDO activa.
 * Ubicación: /app/Data/ActividadDAO.php
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Actividad.php';

class ActividadDAO {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ════════════════════════════════════════════════════════════════
    // CREATE — Registrar un evento en la bitácora
    // ════════════════════════════════════════════════════════════════

    /**
     * Inserta un nuevo registro de auditoría.
     * @return int  ID generado.
     */
    public function crear(Actividad $a): int {
        $sql = "INSERT INTO actividad
                    (usuario_id, proyecto_id, tipo, descripcion, meta)
                VALUES
                    (:usuario_id, :proyecto_id, :tipo, :descripcion, :meta)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id'  => $a->getUsuarioId(),
            ':proyecto_id' => $a->getProyectoId(),
            ':tipo'        => $a->getTipo(),
            ':descripcion' => $a->getDescripcion(),
            ':meta'        => $a->getMeta(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════════
    // READ
    // ════════════════════════════════════════════════════════════════

    /** @return Actividad|null */
    public function obtenerPorId(int $id): ?Actividad {
        $stmt = $this->pdo->prepare("SELECT * FROM actividad WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Devuelve el historial de actividad de un usuario.
     * @return Actividad[]
     */
    public function obtenerPorUsuario(int $usuarioId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM actividad WHERE usuario_id = :uid ORDER BY creado_en DESC"
        );
        $stmt->execute([':uid' => $usuarioId]);
        return array_map([$this, 'hidratar'], $stmt->fetchAll());
    }

    /**
     * Devuelve la actividad relacionada con un proyecto específico.
     * @return Actividad[]
     */
    public function obtenerPorProyecto(int $proyectoId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM actividad WHERE proyecto_id = :pid ORDER BY creado_en DESC"
        );
        $stmt->execute([':pid' => $proyectoId]);
        return array_map([$this, 'hidratar'], $stmt->fetchAll());
    }

    // ════════════════════════════════════════════════════════════════
    // UPDATE — Modificar descripción o metadatos de un evento
    // ════════════════════════════════════════════════════════════════

    /** @return bool */
    public function actualizar(Actividad $a): bool {
        $sql = "UPDATE actividad SET
                    descripcion = :descripcion,
                    meta        = :meta
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':descripcion' => $a->getDescripcion(),
            ':meta'        => $a->getMeta(),
            ':id'          => $a->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // DELETE — Eliminar un registro de auditoría
    // ════════════════════════════════════════════════════════════════

    /** @return bool */
    public function eliminar(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM actividad WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // HIDRATACIÓN
    // ════════════════════════════════════════════════════════════════

    private function hidratar(array $fila): Actividad {
        $a = new Actividad();
        $a->setId((int)$fila['id']);
        $a->setUsuarioId((int)$fila['usuario_id']);
        $a->setProyectoId($fila['proyecto_id'] !== null ? (int)$fila['proyecto_id'] : null);
        $a->setTipo($fila['tipo']);
        $a->setDescripcion($fila['descripcion']);
        $a->setMeta($fila['meta']);
        $a->setCreadoEn($fila['creado_en']);
        return $a;
    }
}