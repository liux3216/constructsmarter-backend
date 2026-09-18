<?php
require_once "/opt/bitnami/apache/htdocs/test/auth/internalAuth.php";
$autoload = "/opt/bitnami/apache2/htdocs/components/vendor/autoload.php";
if (file_exists($autoload)) require_once $autoload;

function cleanText($value) {
    $value = is_null($value) ? "" : (string)$value;
    $value = preg_replace('/\s+/', ' ', trim($value));
    return $value;
}

function excerptText($value, $max = 260) {
    $value = cleanText($value);
    if (strlen($value) <= $max) return $value;
    return substr($value, 0, $max - 3) . "...";
}

function queryTokens($question) {
    $stopWords = ["about", "after", "again", "all", "also", "and", "are", "because", "been", "but", "can", "could", "does", "for", "from", "have", "how", "into", "need", "not", "our", "show", "that", "the", "their", "then", "there", "this", "what", "when", "where", "which", "with", "would", "you", "your", "summarize", "summary", "related", "record", "records", "lead", "leads", "organization", "organizations", "contact", "contacts", "project", "projects", "opportunity", "opportunities", "proposal", "proposals", "work", "works", "called", "named", "guy", "aguy", "person", "people", "someone", "client", "customer", "employee", "employees"];
    $parts = preg_split('/[^a-zA-Z0-9@._-]+/', strtolower($question));
    $rawParts = preg_split('/\s+/', strtolower($question));
    $tokens = [];
    foreach (array_merge($parts, $rawParts) as $part) {
        $part = trim($part);
        $compact = preg_replace('/[^a-z0-9@._-]+/', '', $part);
        foreach (array_unique([$part, $compact]) as $candidate) {
            $candidate = trim($candidate, ".,;:!?()[]{}<>\"'");
            if (strlen($candidate) < 3 || in_array($candidate, $stopWords, true)) continue;
            $tokens[$candidate] = true;
            if (count($tokens) >= 8) break 2;
        }
    }
    return array_keys($tokens);
}

function isCountQuestion($question) {
    return (bool)preg_match('/\b(how many|count|total number|number of|total)\b/i', $question);
}

function sourceMentioned($question, $sourceKey, $label) {
    $question = strtolower($question);
    $words = [strtolower($sourceKey), strtolower($label)];
    if (str_ends_with($sourceKey, "s")) $words[] = substr($sourceKey, 0, -1);
    if (str_ends_with($label, "s")) $words[] = substr(strtolower($label), 0, -1);
    foreach (array_unique($words) as $word) {
        if ($word && preg_match('/\b' . preg_quote($word, '/') . '\b/', $question)) return true;
    }
    return false;
}

function isInternalPersonQuestion($question) {
    return (bool)preg_match('/\b(our|employee|employees|staff|teammate|team member|coworker|co-worker|internal)\b/i', $question);
}

function isGeneralQuestion($question) {
    $dbWords = '/\b(lead|leads|organization|organizations|org|orgs|contact|contacts|project|projects|opportunity|opportunities|proposal|proposals|work|works|user|users|employee|employees|staff|customer|client|record|records|database|db|mariadb|table|tables)\b/i';
    if (preg_match($dbWords, $question)) return false;
    return (bool)preg_match('/\b(today|date|time|weather|who are you|what can you do|help me|explain|define|calculate|write|draft|summarize this|translate)\b/i', $question);
}

function isRecruitmentQuestion($question) {
    return (bool)preg_match('/\b(recruit|recruited|hire|hired|hiring|new employee|new employees|joined|onboard|onboarded)\b/i', $question)
        && (bool)preg_match('/\b(employee|employees|staff|user|users|people|person)\b/i', $question);
}

function getQuestionDateRange($question) {
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
    if (preg_match('/\b(today)\b/i', $question)) {
        return [$now->format('Y-m-d'), $now->modify('+1 day')->format('Y-m-d'), 'today'];
    }
    if (preg_match('/\b(this month|current month)\b/i', $question)) {
        return [$now->modify('first day of this month')->format('Y-m-d'), $now->modify('first day of next month')->format('Y-m-d'), $now->format('F Y')];
    }
    if (preg_match('/\b(last month|previous month)\b/i', $question)) {
        $start = $now->modify('first day of last month');
        return [$start->format('Y-m-d'), $start->modify('first day of next month')->format('Y-m-d'), $start->format('F Y')];
    }
    if (preg_match('/\b(this year|current year)\b/i', $question)) {
        return [$now->format('Y-01-01'), $now->modify('first day of January next year')->format('Y-m-d'), $now->format('Y')];
    }
    return [$now->modify('first day of this month')->format('Y-m-d'), $now->modify('first day of next month')->format('Y-m-d'), $now->format('F Y')];
}

function answerRecruitmentQuestion($db, $question) {
    if (!isRecruitmentQuestion($question)) return null;
    [$start, $end, $label] = getQuestionDateRange($question);
    $rows = $db->all(
        "SELECT `id`, CONCAT_WS(' ', `firstName`, `middleName`, `lastName`) AS `name`, `email`, `role`, `department`, `hireDate`
         FROM `users`
         WHERE `void` = 'no' AND `hireDate` >= ? AND `hireDate` < ?
         ORDER BY `hireDate` DESC, `createdAt` DESC",
        [$start, $end], __FILE__, __LINE__
    );
    $total = count($rows);
    $sources = [];
    foreach (array_slice($rows, 0, 10) as $row) {
        $sources[] = [
            "source" => "Users",
            "sourceKey" => "users",
            "id" => $row["id"],
            "title" => cleanText($row["name"] ?: $row["email"]),
            "summary" => cleanText("Hire date: " . ($row["hireDate"] ?? "") . " | " . ($row["role"] ?? "") . " | " . ($row["department"] ?? "")),
            "url" => "/Users/" . $row["id"],
        ];
    }
    $answer = "You recruited $total employee" . ($total === 1 ? "" : "s") . " in $label.";
    if ($total > 0) {
        $answer .= "

";
        foreach (array_slice($rows, 0, 10) as $row) {
            $name = cleanText($row["name"] ?: $row["email"]);
            $answer .= "- $name" . (!empty($row["hireDate"]) ? " ({$row["hireDate"]})" : "") . "
";
        }
        if ($total > 10) $answer .= "- ...and " . ($total - 10) . " more
";
    }
    return ["answer" => trim($answer), "sources" => $sources];
}

function answerCountQuestion($db, $question, $source, $sources) {
    if (!isCountQuestion($question)) return null;
    $selected = [];
    if ($source !== "all" && array_key_exists($source, $sources)) {
        $selected[] = $source;
    } else {
        foreach ($sources as $key => $config) {
            if (sourceMentioned($question, $key, $config["label"])) $selected[] = $key;
        }
    }
    if (!count($selected)) return null;
    $lines = [];
    $countSources = [];
    foreach ($selected as $key) {
        $config = $sources[$key];
        $voidClause = $config["hasVoid"] ? " WHERE {$config["alias"]}.`void` = 'no'" : "";
        $row = $db->one("SELECT COUNT(*) AS `total` FROM `{$config["table"]}` {$config["alias"]}$voidClause", [], __FILE__, __LINE__);
        $total = (int)($row["total"] ?? 0);
        $lines[] = "There " . ($total === 1 ? "is" : "are") . " $total active " . strtolower($config["label"]) . " record" . ($total === 1 ? "" : "s") . ".";
        $countSources[] = [
            "source" => $config["label"],
            "sourceKey" => $key,
            "id" => null,
            "title" => "Count",
            "summary" => "$total active records",
            "url" => $config["route"],
        ];
    }
    return ["answer" => implode("
", $lines), "sources" => $countSources];
}

function buildLikeWhere($alias, $fields, $tokens, &$params) {
    if (!count($tokens)) return "1 = 1";
    $clauses = [];
    foreach ($tokens as $token) {
        $fieldClauses = [];
        foreach ($fields as $field) {
            $column = preg_match('/[`.(]/', $field) ? $field : "$alias.`$field`";
            $fieldClauses[] = "$column LIKE ?";
            $params[] = "%" . $token . "%";
        }
        $clauses[] = "(" . implode(" OR ", $fieldClauses) . ")";
    }
    return implode(" AND ", $clauses);
}

function fetchRows($db, $sourceKey, $config, $tokens) {
    $params = [];
    $where = buildLikeWhere($config["alias"], $config["searchFields"], $tokens, $params);
    $voidClause = $config["hasVoid"] ? " AND {$config["alias"]}.`void` = 'no'" : "";
    $sql = $config["sql"] . " WHERE $where $voidClause ORDER BY {$config["alias"]}.`createdAt` DESC LIMIT 5";
    $rows = $db->all($sql, $params, __FILE__, __LINE__);
    $results = [];
    foreach ($rows as $row) {
        $results[] = [
            "source" => $config["label"],
            "sourceKey" => $sourceKey,
            "id" => $row["id"] ?? null,
            "title" => cleanText($row["title"] ?? "Untitled"),
            "summary" => excerptText($row["summary"] ?? ""),
            "url" => $config["route"] . "/" . ($row["id"] ?? ""),
        ];
    }
    return $results;
}

function fallbackAnswer($question, $sources) {
    if (!count($sources)) {
        return "I could not find matching records in the selected data source. Try using a more specific name, email, project number, organization, location, or status.";
    }
    $counts = [];
    foreach ($sources as $source) {
        $label = $source["source"];
        $counts[$label] = ($counts[$label] ?? 0) + 1;
    }
    $parts = [];
    foreach ($counts as $label => $count) {
        $parts[] = "$count " . strtolower($label) . " record" . ($count === 1 ? "" : "s");
    }
    $answer = "I found " . implode(", ", $parts) . " related to your request.\n\n";
    foreach (array_slice($sources, 0, 8) as $source) {
        $summary = $source["summary"] ? " - " . $source["summary"] : "";
        $answer .= "- {$source["source"]} #{$source["id"]}: {$source["title"]}{$summary}\n";
    }
    return trim($answer);
}

function getConfiguredOpenAIKey() {
    global $openaiApiKey, $OPENAI_API_KEY, $llmApiKey, $LLM_API_KEY;
    foreach (["OPENAI_API_KEY", "LLM_API_KEY"] as $name) {
        $value = getenv($name);
        if ($value) return trim($value);
        if (array_key_exists($name, $_ENV) && $_ENV[$name]) return trim((string)$_ENV[$name]);
        if (array_key_exists($name, $_SERVER) && $_SERVER[$name]) return trim((string)$_SERVER[$name]);
        if (defined($name) && constant($name)) return trim((string)constant($name));
    }
    foreach ([$openaiApiKey ?? null, $OPENAI_API_KEY ?? null, $llmApiKey ?? null, $LLM_API_KEY ?? null] as $value) {
        if ($value) return trim((string)$value);
    }
    return "";
}

function generateWithLLPhant($question, $sources, &$llmStatus = null) {
    $llmStatus = "not_attempted";
    $apiKey = getConfiguredOpenAIKey();
    if (!$apiKey) {
        $llmStatus = "missing_api_key";
        return null;
    }
    if (!class_exists("LLPhant\\Chat\\OpenAIChat") || !class_exists("LLPhant\\OpenAIConfig")) {
        $llmStatus = "missing_llphant_class";
        return null;
    }
    try {
        $context = count($sources)
            ? json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : "[]";
        $config = new LLPhant\OpenAIConfig();
        $config->apiKey = $apiKey;
        if (property_exists($config, "model")) $config->model = getenv("OPENAI_MODEL") ?: "gpt-4o-mini";
        $chat = new LLPhant\Chat\OpenAIChat($config);
        $prompt = "You are a Help assistant for Construct Smarter. Answer in concise natural language. Use the provided database records when available. If no records are provided, explain that no matching MariaDB records were found and suggest a better query.\n\nQuestion:\n$question\n\nDatabase records:\n$context";
        $answer = trim($chat->generateText($prompt));
        $llmStatus = $answer === "" ? "empty_response" : "used";
        return $answer === "" ? null : $answer;
    } catch (Throwable $e) {
        $llmStatus = "error: " . $e->getMessage();
        error_log(basename(__FILE__) . " " . __LINE__ . " LLPhant generation failed: " . $e->getMessage());
        return null;
    }
}

$question = cleanText($_POST["question"] ?? "");
$source = cleanText($_POST["source"] ?? "all");
if (strlen($question) < 3) {
    http_response_code(400);
    exit(json_encode(["msg" => "Please enter a question."]));
}
if (strlen($question) > 2000) $question = substr($question, 0, 2000);

if (isGeneralQuestion($question)) {
    $llmStatus = null;
    $generated = generateWithLLPhant($question, [], $llmStatus);
    exit(json_encode([
        "answer" => $generated ?: "This question does not appear to need database lookup, and the LLM is not available right now.",
        "sources" => [],
        "usedLLM" => (bool)$generated,
        "llmStatus" => $llmStatus,
        "source" => $source,
    ]));
}

$sources = [
    "users" => [
        "label" => "Users", "route" => "/Users", "table" => "users", "alias" => "u", "hasVoid" => true,
        "searchFields" => ["firstName", "middleName", "lastName", "email", "role", "department", "region", "phoneNumber", "workPhone", "background"],
        "sql" => "SELECT u.`id`, CONCAT_WS(' ', u.`firstName`, u.`middleName`, u.`lastName`) AS `title`, CONCAT_WS(' | ', u.`email`, u.`role`, u.`department`, u.`region`, u.`phoneNumber`, u.`workPhone`, u.`background`) AS `summary` FROM `users` u",
    ],
    "leads" => [
        "label" => "Leads", "route" => "/Leads", "table" => "leads", "alias" => "l", "hasVoid" => true,
        "searchFields" => ["firstName", "middleName", "lastName", "email", "role", "businessPhone", "mobile", "background", "industry", "status", "source", "o.`name`"],
        "sql" => "SELECT l.`id`, CONCAT_WS(' ', l.`firstName`, l.`middleName`, l.`lastName`) AS `title`, CONCAT_WS(' | ', l.`email`, l.`role`, o.`name`, l.`status`, l.`background`) AS `summary` FROM `leads` l LEFT JOIN `organizations` o ON o.`id` = l.`organizationId`",
    ],
    "organizations" => [
        "label" => "Organizations", "route" => "/Organizations", "table" => "organizations", "alias" => "o", "hasVoid" => true,
        "searchFields" => ["name", "website", "phoneNumber", "background", "city", "state"],
        "sql" => "SELECT o.`id`, o.`name` AS `title`, CONCAT_WS(' | ', o.`website`, o.`phoneNumber`, o.`city`, o.`state`, o.`background`) AS `summary` FROM `organizations` o",
    ],
    "contacts" => [
        "label" => "Contacts", "route" => "/Contacts", "table" => "contacts", "alias" => "c", "hasVoid" => true,
        "searchFields" => ["firstName", "middleName", "lastName", "email1", "email2", "role", "phoneNumber", "directNumber", "background", "o.`name`"],
        "sql" => "SELECT c.`id`, CONCAT_WS(' ', c.`firstName`, c.`middleName`, c.`lastName`) AS `title`, CONCAT_WS(' | ', c.`email1`, c.`email2`, c.`role`, o.`name`, c.`background`) AS `summary` FROM `contacts` c LEFT JOIN `organizations` o ON o.`id` = c.`organizationId`",
    ],
    "projects" => [
        "label" => "Projects", "route" => "/Projects", "table" => "projects", "alias" => "p", "hasVoid" => true,
        "searchFields" => ["projectNumber", "clientProjectNumber", "clientPONumber", "pipeline", "subpipeline", "stage", "region", "location", "notes", "description", "o.`name`"],
        "sql" => "SELECT p.`id`, COALESCE(NULLIF(p.`projectNumber`, ''), CONCAT('Project ', p.`id`)) AS `title`, CONCAT_WS(' | ', o.`name`, p.`pipeline`, p.`stage`, p.`region`, p.`location`, p.`description`) AS `summary` FROM `projects` p LEFT JOIN `organizations` o ON o.`id` = p.`organizationId`",
    ],
    "opportunities" => [
        "label" => "Opportunities", "route" => "/Opportunities", "table" => "opportunities", "alias" => "op", "hasVoid" => true,
        "searchFields" => ["opportunityName", "category", "state", "location", "background", "o.`name`"],
        "sql" => "SELECT op.`id`, op.`opportunityName` AS `title`, CONCAT_WS(' | ', o.`name`, op.`category`, op.`state`, op.`location`, op.`background`) AS `summary` FROM `opportunities` op LEFT JOIN `organizations` o ON o.`id` = op.`organizationId`",
    ],
    "proposals" => [
        "label" => "Proposals", "route" => "/Proposals", "table" => "proposals", "alias" => "pr", "hasVoid" => true,
        "searchFields" => ["proposalNumber", "department", "notes", "status", "approverNotes"],
        "sql" => "SELECT pr.`id`, pr.`proposalNumber` AS `title`, CONCAT_WS(' | ', pr.`proposalDate`, pr.`department`, pr.`status`, pr.`total`, pr.`notes`) AS `summary` FROM `proposals` pr",
    ],
    "works" => [
        "label" => "Works", "route" => "/Works", "table" => "works", "alias" => "w", "hasVoid" => true,
        "searchFields" => ["category", "subCategory", "location", "coords", "description"],
        "sql" => "SELECT w.`id`, CONCAT_WS(' - ', w.`category`, w.`subCategory`) AS `title`, CONCAT_WS(' | ', p.`projectNumber`, w.`location`, w.`startTime`, w.`endTime`, w.`description`) AS `summary` FROM `works` w LEFT JOIN `projects` p ON p.`id` = w.`projectId`",
    ],
];

$countAnswer = answerCountQuestion($db, $question, $source, $sources);
if ($countAnswer) {
    exit(json_encode([
        "answer" => $countAnswer["answer"],
        "sources" => $countAnswer["sources"],
        "usedLLM" => false,
        "llmStatus" => "sql_count_answer",
        "source" => $source,
    ]));
}

$tokens = queryTokens($question);
if (!count($tokens)) $tokens = [strtolower($question)];
if (isInternalPersonQuestion($question) && ($source === "all" || !array_key_exists($source, $sources))) {
    $selected = ["users", "contacts", "leads"];
} else {
    $selected = $source === "all" || !array_key_exists($source, $sources) ? array_keys($sources) : [$source];
}
$matches = [];
foreach ($selected as $key) {
    $matches = array_merge($matches, fetchRows($db, $key, $sources[$key], $tokens));
}
$matches = array_slice($matches, 0, 20);
$llmStatus = null;
$generated = generateWithLLPhant($question, $matches, $llmStatus);

exit(json_encode([
    "answer" => $generated ?: fallbackAnswer($question, $matches),
    "sources" => $matches,
    "usedLLM" => (bool)$generated,
    "llmStatus" => $llmStatus,
    "source" => $source,
]));
