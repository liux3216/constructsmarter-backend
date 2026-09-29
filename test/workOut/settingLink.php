<?php
function validateSettingLink($value) {
    if (!is_string($value)) {
        http_response_code(422);
        exit(json_encode(['msg' => 'Enter a valid http:// or https:// exercise URL.']));
    }
    $value = trim($value);
    if ($value === '') return '';
    $parts = parse_url($value);
    if (strlen($value) > 2048 || preg_match('/[\s\\\\]/', $value) || !filter_var($value, FILTER_VALIDATE_URL) || !$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
        http_response_code(422);
        exit(json_encode(['msg' => 'Enter a valid http:// or https:// URL (up to 2048 characters), or leave it blank.']));
    }
    return $value;
}
