<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

function rentalResponse(int $status, array $payload): void {
    http_response_code($status);
    exit(json_encode($payload));
}

function rentalId(): string {
    return bin2hex(random_bytes(16));
}

function rentalRequireString(string $key, int $max = 255): string {
    $value = trim((string)($_POST[$key] ?? ""));
    if ($value === "") {
        throw new InvalidArgumentException("Missing $key.");
    }
    if (mb_strlen($value) > $max) {
        throw new InvalidArgumentException("$key is too long.");
    }
    return $value;
}

function rentalOptionalString(string $key, int $max = 65535): string {
    $value = trim((string)($_POST[$key] ?? ""));
    if (mb_strlen($value) > $max) {
        throw new InvalidArgumentException("$key is too long.");
    }
    return $value;
}

function rentalRequireDate(string $key, bool $allowEmpty = false): ?string {
    $value = trim((string)($_POST[$key] ?? ""));
    if ($value === "") {
        if ($allowEmpty) return null;
        throw new InvalidArgumentException("Missing $key.");
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException("Invalid $key.");
    }
    return $value;
}

function rentalRequireProjectId(): int {
    global $db;
    $value = trim((string)($_POST['projectId'] ?? ''));
    if ($value === '') {
        throw new InvalidArgumentException('Missing projectId.');
    }
    $projectId = (int)$value;
    $row = $db->one('SELECT `id` FROM `projects` WHERE `id` = ? LIMIT 1;', [$projectId], __FILE__, __LINE__);
    if (!$row) {
        throw new InvalidArgumentException('Project not found.');
    }
    return $projectId;
}

function rentalRequireUserId(string $key): string {
    global $db;
    $value = trim((string)($_POST[$key] ?? ''));
    if ($value === '') {
        throw new InvalidArgumentException("Missing $key.");
    }
    $row = $db->one('SELECT `id` FROM `users` WHERE `id` = ? LIMIT 1;', [$value], __FILE__, __LINE__);
    if (!$row) {
        throw new InvalidArgumentException('User not found.');
    }
    return $value;
}

function rentalFind(string $id): array {
    global $db;
    $row = $db->one('SELECT * FROM `rental_statuses` WHERE `id` = ? LIMIT 1;', [$id], __FILE__, __LINE__);
    if (!$row) {
        rentalResponse(404, ['msg' => 'Rental not found.']);
    }
    return $row;
}
