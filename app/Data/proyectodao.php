<?php

/**
 * Componente de Acceso a Datos: ProyectoDAO
 * Responsabilidad: Ejecutar operaciones CRUD sobre la tabla `proyectos`.
 * Invoca el Singleton Database para obtener la conexión PDO activa.
 * Ubicación: /app/Data/ProyectoDAO.php
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/Proyecto.php';

class ProyectoDAO {

  
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

   
    public function crear(Proyecto $p): int {
        $sql = "INSERT INTO proyectos
                    (usuario_id, nombre, descripcion, archivo_nombre,
                     total_puntos, total_triangulos, area_m2, perimetro_m,
                     volumen_m3, cota_min, cota_max, desnivel,
                     centroide_x, centroide_y, centroide_z, estado)
                VALUES
                    (:usuario_id, :nombre, :descripcion, :archivo_nombre,
                     :total_puntos, :total_triangulos, :area_m2, :perimetro_m,
                     :volumen_m3, :cota_min, :cota_max, :desnivel,
                     :centroide_x, :centroide_y, :centroide_z, :estado)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id'       => $p->getUsuarioId(),
            ':nombre'           => $p->getNombre(),
            ':descripcion'      => $p->getDescripcion(),
            ':archivo_nombre'   => $p->getArchivoNombre(),
            ':total_puntos'     => $p->getTotalPuntos(),
            ':total_triangulos' => $p->getTotalTriangulos(),
            ':area_m2'          => $p->getAreaM2(),
            ':perimetro_m'      => $p->getPerimetroM(),
            ':volumen_m3'       => $p->getVolumenM3(),
            ':cota_min'         => $p->getCotaMin(),
            ':cota_max'         => $p->getCotaMax(),
            ':desnivel'         => $p->getDesnivel(),
            ':centroide_x'      => $p->getCentroideX(),
            ':centroide_y'      => $p->getCentroideY(),
            ':centroide_z'      => $p->getCentroideZ(),
            ':estado'           => $p->getEstado(),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    
    public function obtenerPorId(int $id): ?Proyecto {
        $stmt = $this->pdo->prepare("SELECT * FROM proyectos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ? $this->hidratar($fila) : null;
    }

   
    public function obtenerPorUsuario(int $usuarioId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM proyectos WHERE usuario_id = :uid ORDER BY creado_en DESC"
        );
        $stmt->execute([':uid' => $usuarioId]);
        $filas = $stmt->fetchAll();

        return array_map([$this, 'hidratar'], $filas);
    }

    
    public function obtenerTodos(): array {
        $stmt = $this->pdo->query("SELECT * FROM proyectos ORDER BY creado_en DESC");
        return array_map([$this, 'hidratar'], $stmt->fetchAll());
    }

    
    public function actualizar(Proyecto $p): bool {
        $sql = "UPDATE proyectos SET
                    nombre           = :nombre,
                    descripcion      = :descripcion,
                    total_puntos     = :total_puntos,
                    total_triangulos = :total_triangulos,
                    area_m2          = :area_m2,
                    perimetro_m      = :perimetro_m,
                    volumen_m3       = :volumen_m3,
                    cota_min         = :cota_min,
                    cota_max         = :cota_max,
                    desnivel         = :desnivel,
                    centroide_x      = :centroide_x,
                    centroide_y      = :centroide_y,
                    centroide_z      = :centroide_z,
                    estado           = :estado
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nombre'           => $p->getNombre(),
            ':descripcion'      => $p->getDescripcion(),
            ':total_puntos'     => $p->getTotalPuntos(),
            ':total_triangulos' => $p->getTotalTriangulos(),
            ':area_m2'          => $p->getAreaM2(),
            ':perimetro_m'      => $p->getPerimetroM(),
            ':volumen_m3'       => $p->getVolumenM3(),
            ':cota_min'         => $p->getCotaMin(),
            ':cota_max'         => $p->getCotaMax(),
            ':desnivel'         => $p->getDesnivel(),
            ':centroide_x'      => $p->getCentroideX(),
            ':centroide_y'      => $p->getCentroideY(),
            ':centroide_z'      => $p->getCentroideZ(),
            ':estado'           => $p->getEstado(),
            ':id'               => $p->getId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // DELETE — Eliminar un proyecto de la base de datos
    // ════════════════════════════════════════════════════════════════

    /**
     * Elimina un proyecto por ID. Las FK con CASCADE limpian
     * automáticamente archivos, cotizaciones y actividad asociada.
     * @return bool  true si se eliminó la fila.
     */
    public function eliminar(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM proyectos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ════════════════════════════════════════════════════════════════
    // HIDRATACIÓN — Convertir fila SQL → objeto Proyecto
    // ════════════════════════════════════════════════════════════════

    /**
     * Mapea un array asociativo de PDO a un objeto Proyecto completo.
     */
    private function hidratar(array $fila): Proyecto {
        $p = new Proyecto();
        $p->setId((int)$fila['id']);
        $p->setUsuarioId((int)$fila['usuario_id']);
        $p->setNombre($fila['nombre']);
        $p->setDescripcion($fila['descripcion']);
        $p->setArchivoNombre($fila['archivo_nombre']);
        $p->setTotalPuntos((int)$fila['total_puntos']);
        $p->setTotalTriangulos((int)$fila['total_triangulos']);
        $p->setAreaM2((float)$fila['area_m2']);
        $p->setPerimetroM((float)$fila['perimetro_m']);
        $p->setVolumenM3((float)$fila['volumen_m3']);
        $p->setCotaMin((float)$fila['cota_min']);
        $p->setCotaMax((float)$fila['cota_max']);
        $p->setDesnivel((float)$fila['desnivel']);
        $p->setCentroideX((float)$fila['centroide_x']);
        $p->setCentroideY((float)$fila['centroide_y']);
        $p->setCentroideZ((float)$fila['centroide_z']);
        $p->setEstado($fila['estado']);
        $p->setCreadoEn($fila['creado_en']);
        $p->setActualizadoEn($fila['actualizado_en']);
        return $p;
    }
}