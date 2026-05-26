<?php

/**
 * Clase Entidad: Cotizacion
 * Responsabilidad: Representar un presupuesto económico generado para un proyecto.
 * Refleja fielmente la tabla `cotizaciones` de la base de datos MySQL.
 * Ubicación: /app/Core/Cotizacion.php
 */
class Cotizacion {

    // ── Atributos privados (encapsulamiento estricto) ─────────────
    private int     $id;
    private int     $proyectoId;
    private int     $usuarioId;
    private float   $tarifaTierra;
    private float   $tarifaNivelacion;
    private float   $tarifaCerramiento;
    private float   $costoTierra;
    private float   $costoNivelacion;
    private float   $costoCerramiento;
    private float   $total;
    private string  $moneda;
    private ?string $notas;
    private string  $creadoEn;

    // ── Getters ───────────────────────────────────────────────────

    public function getId(): int                  { return $this->id; }
    public function getProyectoId(): int          { return $this->proyectoId; }
    public function getUsuarioId(): int           { return $this->usuarioId; }
    public function getTarifaTierra(): float      { return $this->tarifaTierra; }
    public function getTarifaNivelacion(): float  { return $this->tarifaNivelacion; }
    public function getTarifaCerramiento(): float { return $this->tarifaCerramiento; }
    public function getCostoTierra(): float       { return $this->costoTierra; }
    public function getCostoNivelacion(): float   { return $this->costoNivelacion; }
    public function getCostoCerramiento(): float  { return $this->costoCerramiento; }
    public function getTotal(): float             { return $this->total; }
    public function getMoneda(): string           { return $this->moneda; }
    public function getNotas(): ?string           { return $this->notas; }
    public function getCreadoEn(): string         { return $this->creadoEn; }

    // ── Setters ───────────────────────────────────────────────────

    public function setId(int $id): void                         { $this->id = $id; }
    public function setProyectoId(int $id): void                 { $this->proyectoId = $id; }
    public function setUsuarioId(int $id): void                  { $this->usuarioId = $id; }
    public function setTarifaTierra(float $tarifa): void         { $this->tarifaTierra = $tarifa; }
    public function setTarifaNivelacion(float $tarifa): void     { $this->tarifaNivelacion = $tarifa; }
    public function setTarifaCerramiento(float $tarifa): void    { $this->tarifaCerramiento = $tarifa; }
    public function setCostoTierra(float $costo): void           { $this->costoTierra = $costo; }
    public function setCostoNivelacion(float $costo): void       { $this->costoNivelacion = $costo; }
    public function setCostoCerramiento(float $costo): void      { $this->costoCerramiento = $costo; }
    public function setTotal(float $total): void                 { $this->total = $total; }
    public function setMoneda(string $moneda): void              { $this->moneda = $moneda; }
    public function setNotas(?string $notas): void               { $this->notas = $notas; }
    public function setCreadoEn(string $fecha): void             { $this->creadoEn = $fecha; }
}