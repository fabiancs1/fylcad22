<?php


class Actividad {

    // ── Atributos privados (encapsulamiento estricto) ─────────────
    private int     $id;
    private int     $usuarioId;
    private ?int    $proyectoId;
    private string  $tipo;          // ENUM: proyecto_creado | archivo_exportado | login | etc.
    private ?string $descripcion;
    private ?string $meta;          // JSON opcional con metadatos del evento
    private string  $creadoEn;

    // ── Getters ───────────────────────────────────────────────────

    public function getId(): int              { return $this->id; }
    public function getUsuarioId(): int       { return $this->usuarioId; }
    public function getProyectoId(): ?int     { return $this->proyectoId; }
    public function getTipo(): string         { return $this->tipo; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function getMeta(): ?string        { return $this->meta; }
    public function getCreadoEn(): string     { return $this->creadoEn; }

    // ── Setters ───────────────────────────────────────────────────

    public function setId(int $id): void                    { $this->id = $id; }
    public function setUsuarioId(int $id): void             { $this->usuarioId = $id; }
    public function setProyectoId(?int $id): void           { $this->proyectoId = $id; }
    public function setTipo(string $tipo): void             { $this->tipo = $tipo; }
    public function setDescripcion(?string $desc): void     { $this->descripcion = $desc; }
    public function setMeta(?string $meta): void            { $this->meta = $meta; }
    public function setCreadoEn(string $fecha): void        { $this->creadoEn = $fecha; }
}