<?php

/**
 * Clase Entidad: Sesion
 * Responsabilidad: Representar un token de sesión activo de un usuario autenticado.
 * Refleja fielmente la tabla `sesiones` de la base de datos MySQL.
 * Ubicación: /app/Core/Sesion.php
 */
class Sesion {

    // ── Atributos privados (encapsulamiento estricto) ─────────────
    private int     $id;
    private int     $usuarioId;
    private string  $token;
    private ?string $ip;
    private ?string $userAgent;
    private string  $expiraEn;
    private string  $creadoEn;

    // ── Getters ───────────────────────────────────────────────────

    public function getId(): int              { return $this->id; }
    public function getUsuarioId(): int       { return $this->usuarioId; }
    public function getToken(): string        { return $this->token; }
    public function getIp(): ?string          { return $this->ip; }
    public function getUserAgent(): ?string   { return $this->userAgent; }
    public function getExpiraEn(): string     { return $this->expiraEn; }
    public function getCreadoEn(): string     { return $this->creadoEn; }

    // ── Setters ───────────────────────────────────────────────────

    public function setId(int $id): void                  { $this->id = $id; }
    public function setUsuarioId(int $id): void           { $this->usuarioId = $id; }
    public function setToken(string $token): void         { $this->token = $token; }
    public function setIp(?string $ip): void              { $this->ip = $ip; }
    public function setUserAgent(?string $ua): void       { $this->userAgent = $ua; }
    public function setExpiraEn(string $fecha): void      { $this->expiraEn = $fecha; }
    public function setCreadoEn(string $fecha): void      { $this->creadoEn = $fecha; }
}