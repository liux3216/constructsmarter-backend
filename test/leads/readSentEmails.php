<?php
require_once "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";

use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\ClientManager;

function parseMailAddress(mixed $value): array {
    if ($value instanceof Address) {
        return [
            "email" => strtolower(trim($value->mail)),
            "name" => trim($value->personal),
            "full" => trim($value->full),
        ];
    }

    if (is_array($value)) {
        $email = strtolower(trim((string)($value["mail"] ?? $value["email"] ?? "")));
        $name = trim((string)($value["personal"] ?? $value["name"] ?? ""));
        $full = trim((string)($value["full"] ?? ""));
        if (!$full && $email && $name) {
            $full = $name . " <" . $email . ">";
        } elseif (!$full) {
            $full = $email;
        }
        return ["email" => $email, "name" => $name, "full" => $full];
    }

    if (is_object($value)) {
        $vars = get_object_vars($value);
        $email = strtolower(trim((string)($vars["mail"] ?? $vars["email"] ?? "")));
        $name = trim((string)($vars["personal"] ?? $vars["name"] ?? ""));
        $full = trim((string)($vars["full"] ?? ""));
        if (!$full && method_exists($value, "__toString")) {
            $full = trim((string)$value);
        }
        if (!$full && $email && $name) {
            $full = $name . " <" . $email . ">";
        } elseif (!$full) {
            $full = $email;
        }
        return ["email" => $email, "name" => $name, "full" => $full];
    }

    $full = trim((string)$value);
    $email = "";
    $name = "";
    if (preg_match('/<([^>]+)>/', $full, $matches)) {
        $email = strtolower(trim($matches[1]));
        $name = trim(str_replace($matches[0], "", $full), " \t\n\r\0\x0B\"'");
    } elseif (filter_var($full, FILTER_VALIDATE_EMAIL)) {
        $email = strtolower($full);
    }
    return ["email" => $email, "name" => $name, "full" => $full];
}

function parseAddressAttribute(mixed $attr): array {
    if (!$attr) {
        return [];
    }
    $values = method_exists($attr, "all") ? $attr->all() : [$attr];
    $rows = [];
    foreach ($values as $value) {
        $address = parseMailAddress($value);
        if ($address["email"] || $address["full"]) {
            $rows[] = $address;
        }
    }
    return $rows;
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        jsonResponse(405, ["msg" => "Method Not Allowed"]);
    }

    global $appEmail, $appEmailPassword;
    set_time_limit(25);
    $startedAt = time();
    $limit = array_key_exists("limit", $_POST) ? (int)$_POST["limit"] : 5;
    if ($limit < 1 || $limit > 25) {
        $limit = 5;
    }

    $cm = new ClientManager();
    $client = $cm->make([
        "host" => "imap.mail.yahoo.com",
        "port" => 993,
        "encryption" => "ssl",
        "validate_cert" => true,
        "username" => $appEmail,
        "password" => $appEmailPassword,
        "protocol" => "imap",
    ]);

    $client->connect();
    $folder = $client->getFolder("Sent");
    if (!$folder) {
        jsonResponse(404, ["msg" => "Sent folder not found."]);
    }
    $messages = $folder
        ->query()
        ->all()
        ->leaveUnread()
        ->setFetchBody(false)
        ->setFetchFlags(false)
        ->setFetchOrderDesc()
        ->limit($limit)
        ->get();
    $emails = [];
    foreach ($messages as $message) {
        if (time() - $startedAt > 20) {
            break;
        }
        $date = "";
        $subject = "";
        try {
            $dateAttr = $message->get("date");
            $date = method_exists($dateAttr, "toDate") ? $dateAttr->toDate()->toDateTimeString() : (string)$dateAttr;
        } catch (Throwable $e) {
            $date = "";
        }
        try {
            $subject = (string)$message->get("subject");
        } catch (Throwable $e) {
            $subject = "";
        }
        
        try {
            $to = parseAddressAttribute($message->get("to"));
        } catch (Throwable $e) {
            error_log("to parse failed: " . $e->getMessage());
            $to = [];
        }
        try {
            $cc = parseAddressAttribute($message->get("cc"));
        } catch (Throwable $e) {
            error_log("cc parse failed: " . $e->getMessage());
            $cc = [];
        }
        try {
            $bcc = parseAddressAttribute($message->get("bcc"));
        } catch (Throwable $e) {
            error_log("bcc parse failed: " . $e->getMessage());
            $bcc = [];
        }

        $emails[] = [
            "date" => $date,
            "subject" => $subject,
            "to" => $to,
            "cc" => $cc,
            "bcc" => $bcc,
        ];
    }

    jsonResponse(200, [
        "limit" => $limit,
        "total" => count($emails),
        "emails" => $emails,
    ]);
} catch (Throwable $e) {
    error_log($e);
    jsonResponse(500, ["msg" => "Unable to read sent emails."]);
}
