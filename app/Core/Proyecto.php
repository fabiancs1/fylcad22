<?php

class Proyecto {

    // ── Atributos privados (encapsulamiento estricto) ─────────────
    private int     $id;
    private int     $usuarioId;
    private string  $nombre;
    private ?string $descripcion;
    private ?string $archivoNombre;
    private int     $totalPuntos;
    private int     $totalTriangulos;
    private float   $areaM2;
    private float   $perimetroM;
    private float   $volumenM3;
    private float   $cotaMin;
    private float   $cotaMax;
    private float   $desnivel;
    private float   $centroideX;
    private float   $centroideY;
    private float   $centroideZ;
    private string  $estado;        // 'borrador' | 'completo' | 'archivado'
    private string  $creadoEn;
    private string  $actualizadoEn;

    // ── Getters ───────────────────────────────────────────────────

    public function getId(): int                { return $this->id; }
    public function getUsuarioId(): int         { return $this->usuarioId; }
    public function getNombre(): string         { return $this->nombre; }
    public function getDescripcion(): ?string   { return $this->descripcion; }
    public function getArchivoNombre(): ?string { return $this->archivoNombre; }
    public function getTotalPuntos(): int       { return $this->totalPuntos; }
    public function getTotalTriangulos(): int   { return $this->totalTriangulos; }
    public function getAreaM2(): float          { return $this->areaM2; }
    public function getPerimetroM(): float      { return $this->perimetroM; }
    public function getVolumenM3(): float       { return $this->volumenM3; }
    public function getCotaMin(): float         { return $this->cotaMin; }
    public function getCotaMax(): float         { return $this->cotaMax; }
    public function getDesnivel(): float        { return $this->desnivel; }
    public function getCentroideX(): float      { return $this->centroideX; }
    public function getCentroideY(): float      { return $this->centroideY; }
    public function getCentroideZ(): float      { return $this->centroideZ; }
    public function getEstado(): string         { return $this->estado; }
    public function getCreadoEn(): string       { return $this->creadoEn; }
    public function getActualizadoEn(): string  { return $this->actualizadoEn; }

    // ── Setters ───────────────────────────────────────────────────

    public function setId(int $id): void                        { $this->id = $id; }
    public function setUsuarioId(int $id): void                 { $this->usuarioId = $id; }
    public function setNombre(string $nombre): void             { $this->nombre = $nombre; }
    public function setDescripcion(?string $desc): void         { $this->descripcion = $desc; }
    public function setArchivoNombre(?string $archivo): void    { $this->archivoNombre = $archivo; }
    public function setTotalPuntos(int $puntos): void           { $this->totalPuntos = $puntos; }
    public function setTotalTriangulos(int $tri): void          { $this->totalTriangulos = $tri; }
    public function setAreaM2(float $area): void                { $this->areaM2 = $area; }
    public function setPerimetroM(float $per): void             { $this->perimetroM = $per; }
    public function setVolumenM3(float $vol): void              { $this->volumenM3 = $vol; }
    public function setCotaMin(float $cota): void               { $this->cotaMin = $cota; }
    public function setCotaMax(float $cota): void               { $this->cotaMax = $cota; }
    public function setDesnivel(float $des): void               { $this->desnivel = $des; }
    public function setCentroideX(float $x): void               { $this->centroideX = $x; }
    public function setCentroideY(float $y): void               { $this->centroideY = $y; }
    public function setCentroideZ(float $z): void               { $this->centroideZ = $z; }
    public function setEstado(string $estado): void             { $this->estado = $estado; }
    public function setCreadoEn(string $fecha): void            { $this->creadoEn = $fecha; }
    public function setActualizadoEn(string $fecha): void       { $this->actualizadoEn = $fecha; }
}