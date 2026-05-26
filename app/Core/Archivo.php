<?php

/**
 * Clase Entidad: Archivo
 * Responsabilidad: Representar un archivo CSV vinculado a un proyecto topográfico.
 * Refleja fielmente la tabla `archivos` de la base de datos MySQL.
 * Ubicación: /app/Core/Archivo.php
 */
class Archivo {

    // ── Atributos privados (encapsulamiento estricto) ─────────────
    private int    $id;
    private int    $proyectoId;
    private string $nombre;
    private string $contenido;
    private float  $tamanoKb;
    private string $creadoEn;

    // ── Getters ───────────────────────────────────────────────────

    public function getId(): int           { return $this->id; }
    public function getProyectoId(): int   { return $this->proyectoId; }
    public function getNombre(): string    { return $this->nombre; }
    public function getContenido(): string { return $this->contenido; }
    public function getTamanoKb(): float   { return $this->tamanoKb; }
    public function getCreadoEn(): string  { return $this->creadoEn; }

    // ── Setters ───────────────────────────────────────────────────

    public function setId(int $id): void                    { $this->id = $id; }
    public function setProyectoId(int $id): void            { $this->proyectoId = $id; }
    public function setNombre(string $nombre): void         { $this->nombre = $nombre; }
    public function setContenido(string $contenido): void   { $this->contenido = $contenido; }
    public function setTamanoKb(float $tamano): void        { $this->tamanoKb = $tamano; }
    public function setCreadoEn(string $fecha): void        { $this->creadoEn = $fecha; }
}