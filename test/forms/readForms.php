<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
/* ---------- params ---------- */
$status = $_POST["status"] ?? "";
$type   = $_POST["type"] ?? "";
$page   = isset($_POST["page"])  ? (int)$_POST["page"]  : 1;
$limit  = isset($_POST["limit"]) ? (int)$_POST["limit"] : 10;
if ($page < 1)  $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;
$offset = ($page - 1) * $limit;
/* ---------- validate ---------- */
if ($status !== "" && !in_array($status, ["draft", "published"], true)) {
  http_response_code(407);
  exit(json_encode(["error" => "Invalid status"]));
}
/* ---------- where ---------- */
$where  = [];
$params = [];
if($status !== ""){
  $where[]  = "`status` = ?";
  $params[] = $status;
}
if($type !== ""){
  $where[]  = "`type` = ?";
  $params[] = $type;
}
$whereSql = $where ? " WHERE ".implode(" AND ", $where) : "";
/* ---------- count ---------- */
$totalRow = $db->one("SELECT COUNT(*) AS `total` FROM `forms` $whereSql;", $params, __FILE__, __LINE__);
$total = (int)($totalRow["total"] ?? 0);
/* ---------- page overflow guard ---------- */
$maxPage = max(1, (int)ceil($total / $limit));
if ($page > $maxPage) {
  $page = $maxPage;
  $offset = ($page - 1) * $limit;
}
/* ---------- data ---------- */
$dataSql = "SELECT `id`, `name`, `type`, `status`, `createdAt`, `updatedAt` FROM `forms`$whereSql ORDER BY `updatedAt` DESC LIMIT $limit OFFSET $offset;";
$items = $db->all($dataSql, $params, __FILE__, __LINE__);
/* ---------- response ---------- */
exit(json_encode([
  "items" => $items,
  "page"  => $page,
  "limit" => $limit,
  "total" => $total
]));