<?php

/**
 * Clase Entidad: Usuario
 * Responsabilidad: Representar un usuario del sistema FYLCAD.
 * Refleja fielmente la tabla `usuarios` de la base de datos MySQL.
 * Ubicación: /app/Core/Usuario.php
 */
class Usuario {

    // ── Atributos privados (encapsulamiento estricto) ─────────────
    private int     $id;
    private string  $nombre;
    private string  $email;
    private string  $password;
    private string  $plan;          // 'free' | 'premium'
    private int     $activo;        // 1 = activo, 0 = suspendido
    private string  $avatarColor;
    private ?string $ultimoAcceso;
    private string  $creadoEn;
    private string  $actualizadoEn;
    private ?string $resetToken;
    private ?string $resetExpira;

    // ── Getters ───────────────────────────────────────────────────

    public function getId(): int            { return $this->id; }
    public function getNombre(): string     { return $this->nombre; }
    public function getEmail(): string      { return $this->email; }
    public function getPassword(): string   { return $this->password; }
    public function getPlan(): string       { return $this->plan; }
    public function getActivo(): int        { return $this->activo; }
    public function getAvatarColor(): string{ return $this->avatarColor; }
    public function getUltimoAcceso(): ?string { return $this->ultimoAcceso; }
    public function getCreadoEn(): string   { return $this->creadoEn; }
    public function getActualizadoEn(): string { return $this->actualizadoEn; }
    public function getResetToken(): ?string{ return $this->resetToken; }
    public function getResetExpira(): ?string { return $this->resetExpira; }

    // ── Setters ───────────────────────────────────────────────────

    public function setId(int $id): void                        { $this->id = $id; }
    public function setNombre(string $nombre): void             { $this->nombre = $nombre; }
    public function setEmail(string $email): void               { $this->email = $email; }
    public function setPassword(string $password): void         { $this->password = $password; }
    public function setPlan(string $plan): void                 { $this->plan = $plan; }
    public function setActivo(int $activo): void                { $this->activo = $activo; }
    public function setAvatarColor(string $color): void        { $this->avatarColor = $color; }
    public function setUltimoAcceso(?string $fecha): void       { $this->ultimoAcceso = $fecha; }
    public function setCreadoEn(string $fecha): void            { $this->creadoEn = $fecha; }
    public function setActualizadoEn(string $fecha): void       { $this->actualizadoEn = $fecha; }
    public function setResetToken(?string $token): void         { $this->resetToken = $token; }
    public function setResetExpira(?string $fecha): void        { $this->resetExpira = $fecha; }
}