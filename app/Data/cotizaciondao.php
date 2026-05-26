<?php

/**
 * Componente de Acceso a Datos: CotizacionDAO
 * Responsabilidad: Ejecutar operaciones CRUD sobre la tabla `cotizaciones`.
 * Invoca el Singleton Database para obtener la conexión PDO activa.
 * Ubicación: /app/Data/CotizacionDAO.php
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Cotizacion.php';

class CotizacionDAO {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ════════════════════════════════════════════════════════════════
    // CREATE
    // ════════════════════════════════════════════════════════════════

    /**
     * Persiste una cotización calculada para un proyecto.
     * @return int  ID generado por AUTO_INCREMENT.
     */
    public function crear(Cotizacion $c): int {
        $sql = "INSERT INTO cotizaciones
                    (proyecto_id, usuario_id, tarifa_tierra, tarifa_nivelacion,
                     tarifa_cerramiento, costo_tierra, costo_nivelacion,
                     costo_cerramiento, total, moneda, notas)
                VALUES
                    (:proyecto_id, :usuario_id, :tarifa_tierra, :tarifa_nivelacion,
                     :tarifa_cerramiento, :costo_tierra, :costo_nivelacion,
                     :costo_cerramiento, :total, :moneda, :notas)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':proyecto_id'        => $c->getProyectoId(),
            ':usuario_id'         => $c->getUsuarioId(),
            ':tarifa_tierra'      => $c->getTarifaTierra(),
            ':tarifa_nivelacion'  => $c->getTarifaNivelacion(),
            ':tarifa_cerramiento' => $c->getTarifaCerramiento(),
            ':costo_tierra'       => $c->getCostoTierra(),
            ':costo_nivelacion'   => $c->getCostoNivelacion(),
            ':costo_cerramiento'  => $c->getCostoCerramiento(),
            ':total'              => $c->getTotal(),
            ':moneda'             => $c->getMoneda(),
            ':notas'              => $c->getNotas(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════════
    // READ
    // ════════════════════════════════════════════════════════════════

    /** @return Cotizacion|null */
    public function obtenerPorId(int $id): ?Cotizacion {
        $stmt = $this->pdo->prepare("SELECT * FROM cotizaciones WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();
        return $fila ? $this->hidratar($fila) : null;
    }

    /**
     * Devuelve todas las cotizaciones de un proyecto dado.
     * @return Cotizacion[]
     */
    public function obtenerPorProyecto(int $proyectoId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cotizaciones WHERE proyecto_id = :pid ORDER BY creado_en DESC"
        );
        $stmt->execute([':pid' => $proyectoId]);
        return array_map([$this, 'hidratar'], $stmt->fetchAll());
    }

    // ════════════════════════════════════════════════════════════════
    // UPDATE
    // ════════════════════════════════════════════════════════════════

    /** @return bool */
    public function actualizar(Cotizacion $c): bool {
        $sql = "UPDATE cotizaciones SET
                    tarifa_tierra      = :tarifa_tierra,
                    tarifa_nivelacion  = :tarifa_nivelacion,
                    tarifa_cerramiento = :tarifa_cerramiento,
                    costo_tierra       = :costo_tierra,
                    costo_nivelacion   = :costo_nivelacion,
                    costo_cerramiento  = :costo_cerramiento,
                    total              = :total,
                    notas              = :notas
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tarifa_tierra'      => $c->getTarifaTierra(),
            ':tarifa_nivelacion'  => $c->getTarifaNivelacion(),
            ':tarifa_cerramiento' => $c->getTarifaCerramiento(),
            ':costo_tierra'       => $c->getCostoTierra(),
            ':costo_nivelacion'   => $c->getCostoNivelacion(),
            ':costo_cerramiento'  => $c->getCostoCerramiento(),
            ':total'              => $c->getTotal(),
            ':notas'              => $c->getNotas(),
            ':id'                 => $c->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // DELETE
    // ════════════════════════════════════════════════════════════════

    /** @return bool */
    public function eliminar(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM cotizaciones WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // HIDRATACIÓN
    // ════════════════════════════════════════════════════════════════

    private function hidratar(array $fila): Cotizacion {
        $c = new Cotizacion();
        $c->setId((int)$fila['id']);
        $c->setProyectoId((int)$fila['proyecto_id']);
        $c->setUsuarioId((int)$fila['usuario_id']);
        $c->setTarifaTierra((float)$fila['tarifa_tierra']);
        $c->setTarifaNivelacion((float)$fila['tarifa_nivelacion']);
        $c->setTarifaCerramiento((float)$fila['tarifa_cerramiento']);
        $c->setCostoTierra((float)$fila['costo_tierra']);
        $c->setCostoNivelacion((float)$fila['costo_nivelacion']);
        $c->setCostoCerramiento((float)$fila['costo_cerramiento']);
        $c->setTotal((float)$fila['total']);
        $c->setMoneda($fila['moneda']);
        $c->setNotas($fila['notas']);
        $c->setCreadoEn($fila['creado_en']);
        return $c;
    }
}