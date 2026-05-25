<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
//-------------------------------------------------
$id = (int)($_POST["id"] ?? 0);
if($id <= 0){
    http_response_code(400);
    exit(json_encode(["msg" => "Missing id."]));
}
//-------------------------------------------------
$rows = $db->all(
    "SELECT `a`.`id`
    FROM `assignments` `a`
    LEFT JOIN `works` `w` ON `w`.`id` = `a`.`workId`
    WHERE `w`.`projectId` = ?
    AND `w`.`void` <> ?
    AND `a`.`void` <> ?
    ORDER BY `a`.`createdAt`;",
    [$id, "yes", "yes"],
    __FILE__,
    __LINE__
);
foreach($rows as &$row){
    $forms = $db->all(
        "SELECT
            COALESCE(NULLIF(`title`, ''), `formName`) AS `form`,
            `content`
        FROM `assignment_forms`
        WHERE `assignmentId` = ?
        ORDER BY `updatedAt` DESC, `createdAt` DESC;",
        [$row["id"]],
        __FILE__,
        __LINE__
    );
    $row["forms"] = json_encode($forms);
}
unset($row);
echo json_encode($rows);
