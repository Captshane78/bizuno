<?php
/*
 * Workforce helpers with no database access. Ported from Dreamweaver Workforce
 * (DW-Gemini-TC-APP): time math, geofence, CSV, and Gemini prompt text.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/lib.php
 */

namespace bizuno;

function wfH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function wfDuration(string $start, ?string $end = null): string
{
    $from = strtotime($start);
    $to   = $end === null || $end === '' ? time() : strtotime($end);
    if ($from === false || $to === false || $to < $from) { return '00:00:00'; }
    $seconds = $to - $from;
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

function wfSeconds(string $hhmmss): int
{
    $parts = explode(':', $hhmmss);
    if (count($parts) !== 3) { return 0; }
    return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
}

function wfFormatHours(int $seconds): string
{
    if ($seconds < 0) { $seconds = 0; }
    $hours = intdiv($seconds, 3600);
    $mins  = intdiv($seconds % 3600, 60);
    return $hours.'h '.$mins.'m';
}

function wfHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a  = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * @return array{ok:bool,meters:float}
 */
function wfInsideFence(bool $enabled, float $fenceLat, float $fenceLng, float $radius, ?float $hereLat, ?float $hereLng): array
{
    if (!$enabled) { return ['ok'=>true, 'meters'=>0.0]; }
    if ($hereLat === null || $hereLng === null) { return ['ok'=>false, 'meters'=>-1.0]; }
    $meters = wfHaversine($hereLat, $hereLng, $fenceLat, $fenceLng);
    return ['ok'=>$meters <= $radius, 'meters'=>$meters];
}

function wfPeriodStart(string $period, ?int $now = null): ?string
{
    $now = $now ?? time();
    switch ($period) {
        case 'today': return date('Y-m-d 00:00:00', $now);
        case 'week':
            $dow = (int)date('w', $now);
            return date('Y-m-d 00:00:00', $now - ($dow * 86400));
        case 'month': return date('Y-m-01 00:00:00', $now);
        default: return null;
    }
}

function wfDateBound(string $raw, bool $startOfDay): string
{
    $raw = trim(str_replace('T', ' ', $raw));
    if ($raw === '') { return ''; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m)) {
        $day = substr($m[0], 0, 10);
        return $startOfDay ? $day.' 00:00:00' : $day.' 23:59:59';
    }
    $ts = strtotime($raw);
    if ($ts === false) { return ''; }
    return date($startOfDay ? 'Y-m-d 00:00:00' : 'Y-m-d 23:59:59', $ts);
}

function wfDateTime(string $raw): string
{
    $raw = trim(str_replace('T', ' ', $raw));
    if ($raw === '') { return ''; }
    $ts = strtotime($raw);
    if ($ts === false) { return ''; }
    return date('Y-m-d H:i:s', $ts);
}

function wfCsvCell($value): string
{
    $text = (string)$value;
    $first = $text === '' ? '' : $text[0];
    if (preg_match('/^\s*[=+\-@]/', $text) || $first === "\t" || $first === "\r") {
        $text = "'".$text;
    }
    return '"'.str_replace('"', '""', $text).'"';
}

function wfCsv(array $header, array $rows): string
{
    $cell = __NAMESPACE__.'\\wfCsvCell';
    $lines = [implode(',', array_map($cell, $header))];
    foreach ($rows as $row) { $lines[] = implode(',', array_map($cell, $row)); }
    return implode("\n", $lines)."\n";
}

function wfShow(array $row): array
{
    $out = [];
    foreach ($row as $key => $value) {
        if ($key === 'id' || is_int($value) || is_float($value)) {
            $out[$key] = $value;
            continue;
        }
        $out[$key] = wfH($value);
    }
    return $out;
}

/**
 * Keep a reported GPS fix only when both numbers are inside the valid range.
 * @return array{0:?float,1:?float}
 */
function wfFix(?float $lat, ?float $lng): array
{
    if ($lat === null || $lng === null) { return [null, null]; }
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) { return [null, null]; }
    return [$lat, $lng];
}

function wfSafeHtml(string $html): string
{
    $allowed = ['h1'=>true, 'h2'=>true, 'h3'=>true, 'p'=>true, 'ul'=>true, 'ol'=>true, 'li'=>true, 'strong'=>true, 'em'=>true, 'b'=>true, 'i'=>true, 'br'=>true];
    if (!class_exists(\DOMDocument::class)) {
        $html = strip_tags($html, '<h1><h2><h3><p><ul><ol><li><strong><em><b><i><br>');
        return preg_replace('/<\s*([a-z0-9]+)\b[^>]*>/i', '<$1>', $html) ?? '';
    }
    $doc = new \DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'.$html.'</body></html>',
        LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) { return ''; }
    wfScrubNodes($body, $allowed);
    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function wfScrubNodes(\DOMNode $node, array $allowed): void
{
    $children = [];
    foreach ($node->childNodes as $child) { $children[] = $child; }
    foreach ($children as $child) {
        if (!$child instanceof \DOMElement) { continue; }
        wfScrubNodes($child, $allowed);
        if (!isset($allowed[strtolower($child->tagName)])) {
            while ($child->firstChild) { $node->insertBefore($child->firstChild, $child); }
            $node->removeChild($child);
            continue;
        }
        if ($child->attributes) {
            while ($child->attributes->length > 0) {
                $child->removeAttributeNode($child->attributes->item(0));
            }
        }
    }
}

function wfEmailAllowed(string $email, string $domain, array $whitelist): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !str_contains($email, '@')) { return false; }
    $domain = strtolower(trim($domain, " \t@"));
    if ($domain !== '' && str_ends_with($email, '@'.$domain)) { return true; }
    foreach ($whitelist as $allowed) {
        if (strtolower(trim((string)$allowed)) === $email) { return true; }
    }
    return false;
}

function wfHolidays(): array
{
    return [
        ['date'=>'2024-01-01', 'name'=>"New Year's Day", 'type'=>'holiday'],
        ['date'=>'2024-05-27', 'name'=>'Memorial Day', 'type'=>'holiday'],
        ['date'=>'2024-07-04', 'name'=>'Independence Day', 'type'=>'holiday'],
        ['date'=>'2024-09-02', 'name'=>'Labor Day', 'type'=>'holiday'],
        ['date'=>'2024-11-28', 'name'=>'Thanksgiving', 'type'=>'holiday'],
        ['date'=>'2024-12-25', 'name'=>'Christmas Day', 'type'=>'holiday'],
        ['date'=>'2025-01-01', 'name'=>"New Year's Day", 'type'=>'holiday'],
        ['date'=>'2025-05-26', 'name'=>'Memorial Day', 'type'=>'holiday'],
        ['date'=>'2025-07-04', 'name'=>'Independence Day', 'type'=>'holiday'],
        ['date'=>'2025-09-01', 'name'=>'Labor Day', 'type'=>'holiday'],
        ['date'=>'2025-11-27', 'name'=>'Thanksgiving', 'type'=>'holiday'],
        ['date'=>'2025-12-25', 'name'=>'Christmas Day', 'type'=>'holiday'],
        ['date'=>'2026-01-01', 'name'=>"New Year's Day", 'type'=>'holiday'],
        ['date'=>'2026-04-03', 'name'=>'Good Friday', 'type'=>'holiday'],
        ['date'=>'2026-04-05', 'name'=>'Easter Sunday', 'type'=>'holiday'],
        ['date'=>'2026-05-25', 'name'=>'Memorial Day', 'type'=>'holiday'],
        ['date'=>'2026-07-04', 'name'=>'Independence Day', 'type'=>'holiday'],
        ['date'=>'2026-09-07', 'name'=>'Labor Day', 'type'=>'holiday'],
        ['date'=>'2026-11-26', 'name'=>'Thanksgiving', 'type'=>'holiday'],
        ['date'=>'2026-12-25', 'name'=>'Christmas Day', 'type'=>'holiday'],
    ];
}

function wfPromptDaily(string $name, string $durationText, string $times, string $tasks, string $logs): string
{
    return "You are a productivity coach. Generate a brief, encouraging, and insightful daily work summary for an employee named {$name}.\n"
        ."The summary should be formatted in simple HTML. Use tags like <h2> for headlines, <p> for paragraphs, and <strong> for bold text. Do not include <html> or <body> tags.\n"
        ."Start with a positive headline in an <h2> tag.\n"
        ."Mention the total work duration, the number of completed tasks, and highlight the specific work logs (what they built/did).\n"
        ."Provide one or two sentences of positive reinforcement based on their accomplishments and a small, actionable tip for tomorrow.\n\n"
        ."Here is the data:\n{$durationText}\n{$times}\n{$tasks}\n{$logs}\n\nGenerate the summary.";
}

function wfPromptPayroll(string $start, string $end, string $reportData): string
{
    return "You are an HR payroll assistant. Generate a clear and concise payroll report for the period from {$start} to {$end} based on the following data.\n"
        ."The report should be formatted in simple HTML. Use tags like <h1>, <h3>, <p>, and <strong>. Do not include <html> or <body> tags.\n"
        ."Start with a top-level heading \"Payroll Report: {$start} - {$end}\" in an <h1> tag.\n"
        ."For each employee with recorded hours, tasks, or work logs, create a section with their name in an <h3> tag, then list their total hours and a summary of their work logs.\n"
        ."Conclude with a \"Team Summary\" section that includes the total hours worked and total tasks completed by everyone combined.\n\n"
        ."Here is the data:\n{$reportData}\n\nGenerate the report.";
}

function wfPromptTasks(string $start, string $end, string $reportData): string
{
    return "You are an operations manager. Generate a comprehensive Task Report for the period from {$start} to {$end} based on the following data.\n"
        ."The report should be formatted in simple HTML. Use tags like <h1>, <h3>, <p>, <ul>, and <li>. Do not include <html> or <body> tags.\n"
        ."Start with a top-level heading \"Task Performance Report: {$start} - {$end}\" in an <h1> tag.\n"
        ."For each employee, create a section with their name in an <h3> tag, then provide a summary of their tasks (total, completed, pending).\n"
        ."List all their tasks with their status.\n"
        ."Conclude with a \"General Observations\" section summarizing the overall team productivity and any bottlenecks identified.\n\n"
        ."Here is the data:\n{$reportData}\n\nGenerate the report.";
}

function wfPromptLogs(string $start, string $end, string $reportData): string
{
    return "You are a production manager. Generate a detailed Work Log Report for the period from {$start} to {$end} based on the following data.\n"
        ."The report should be formatted in simple HTML. Use tags like <h1>, <h3>, <p>, <ul>, and <li>. Do not include <html> or <body> tags.\n"
        ."Start with a top-level heading \"Work Log Report: {$start} - {$end}\" in an <h1> tag.\n"
        ."For each employee, create a section with their name in an <h3> tag, then provide a summary of what they built or did and the recorded durations.\n"
        ."Conclude with a \"Production Summary\" section summarizing the overall team output and any notable achievements or areas for improvement.\n\n"
        ."Here is the data:\n{$reportData}\n\nGenerate the report.";
}
