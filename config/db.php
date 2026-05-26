<?php


require_once __DIR__ . '/../app/Core/Database.php';

function getDB(): PDO {
    return Database::getInstance()->getConnection();
}