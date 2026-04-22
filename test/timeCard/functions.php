<?php
function defaultEmptyData():array{
    return [
        "form" => array_fill(0, 7, ["inOut" => [], "notes" => ""]),
        "status" => "Created",
    ];
}
function isValidWeekNum(string $week): bool {
    // must be exactly 6 digits e.g. "202509"
    if (!preg_match('/^\d{6}$/', $week)) return false;
    $year  = (int)substr($week, 0, 4);
    $weekN = (int)substr($week, 4, 2);
    // week must be between 01 and 53
    if ($weekN < 1 || $weekN > 53) return false;
    // check week actually exists in that ISO year
    // (some years have 52 weeks, some have 53)
    $maxWeek = (int)(new DateTime())->setISODate($year, 53)->format('W') === 53 ? 53 : 52;
    return $weekN <= $maxWeek;
}
function getDayName(int $dayIndex): string {
    return ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"][$dayIndex] ?? "Unknown";
}
function getDateFromWeekNum(string $week, int $dayIndex): string {
    $year  = (int)substr($week, 0, 4);
    $weekN = (int)substr($week, 4, 2);
    $monday = new DateTime();
    $monday->setISODate($year, $weekN, 1 + $dayIndex);
    return $monday->format("Y-m-d");
}