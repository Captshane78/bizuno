<?php
/*
 * Workforce database, mail, and Gemini calls.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/common.php
 */

namespace bizuno;

if (!function_exists(__NAMESPACE__.'\\wfDuration')) { require_once __DIR__.'/lib.php'; }

function wfBoot(): void
{
    static $done = false;
    if ($done) { return; }
    $done = true;
    wfEnsureTables();
}

function wfTbl(string $short): string
{
    return BIZUNO_DB_PREFIX.'workforce_'.$short;
}

function wfEnsureTables(): void
{
    if (!defined('BIZUNO_DB_PREFIX')) { return; }
    $tables = [
        'time' => "CREATE TABLE IF NOT EXISTS `".wfTbl('time')."` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `start_time` DATETIME NOT NULL,
            `end_time` DATETIME NULL DEFAULT NULL,
            `duration` VARCHAR(16) NOT NULL DEFAULT '00:00:00',
            PRIMARY KEY (`id`),
            KEY `user_open` (`user_id`, `end_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'task' => "CREATE TABLE IF NOT EXISTS `".wfTbl('task')."` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `task_text` TEXT NOT NULL,
            `completed` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_created` (`user_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'log' => "CREATE TABLE IF NOT EXISTS `".wfTbl('log')."` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `log_text` TEXT NOT NULL,
            `part_number` VARCHAR(64) NOT NULL DEFAULT '',
            `quantity` VARCHAR(32) NOT NULL DEFAULT '',
            `duration` VARCHAR(64) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_created` (`user_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'supply' => "CREATE TABLE IF NOT EXISTS `".wfTbl('supply')."` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `part` VARCHAR(128) NOT NULL,
            `quantity` VARCHAR(32) NOT NULL DEFAULT '',
            `reason` TEXT NOT NULL,
            `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
            `source` VARCHAR(128) NOT NULL DEFAULT '',
            `requested_at` DATETIME NOT NULL,
            `handled_at` DATETIME NULL DEFAULT NULL,
            `handled_by` VARCHAR(128) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `status_req` (`status`, `requested_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'leave' => "CREATE TABLE IF NOT EXISTS `".wfTbl('leave')."` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `leave_type` VARCHAR(16) NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `reason` TEXT NOT NULL,
            `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
            `requested_at` DATETIME NOT NULL,
            `handled_at` DATETIME NULL DEFAULT NULL,
            `handled_by` VARCHAR(128) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `status_req` (`status`, `requested_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'event' => "CREATE TABLE IF NOT EXISTS `".wfTbl('event')."` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event_date` DATE NOT NULL,
            `event_name` VARCHAR(128) NOT NULL,
            `event_type` VARCHAR(16) NOT NULL DEFAULT 'event',
            `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `event_date` (`event_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'allow' => "CREATE TABLE IF NOT EXISTS `".wfTbl('allow')."` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(128) NOT NULL,
            `added_by` VARCHAR(128) NOT NULL DEFAULT '',
            `added_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tables as $sql) { dbGetResult($sql); }
}

function wfSet(string $group, string $key, $default = '')
{
    $val = getModuleCache('workforce', 'settings', $group, $key, null);
    if ($val === null || $val === '') { return $default; }
    return $val;
}

function wfUserId(): int
{
    return (int)getUserCache('profile', 'userID', false, 0);
}

function wfUserName(int $id = 0): string
{
    if ($id < 1) { $id = wfUserId(); }
    $row = dbGetRow(BIZUNO_DB_PREFIX.'contacts', 'id='.(int)$id);
    if (empty($row)) { return 'User '.$id; }
    return wfContactName($row);
}

function wfContactName(array $row): string
{
    $last  = trim((string)($row['contact_last'] ?? ''));
    $first = trim((string)($row['contact_first'] ?? ''));
    if ($last !== '' || $first !== '') {
        return trim($last.($last !== '' && $first !== '' ? ', ' : '').$first);
    }
    $primary = trim((string)($row['primary_name'] ?? ''));
    return $primary !== '' ? $primary : ('User '.($row['id'] ?? ''));
}

function wfUsers(bool $activeOnly = true): array
{
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', "ctype_u='1'", 'contact_last, primary_name', ['id', 'inactive', 'primary_name', 'contact_last', 'contact_first', 'email']);
    if (!is_array($rows)) { return []; }
    if (!$activeOnly) { return $rows; }
    return array_values(array_filter($rows, function ($row) { return (string)($row['inactive'] ?? '0') === '0'; }));
}

function wfGuard(string $sec, int $level = 1, bool $verbose = true): int
{
    $ok = validateAccess($sec, $level, $verbose);
    return $ok ? (int)$ok : 0;
}

function wfCan(string $sec, int $level = 1): bool
{
    return wfGuard($sec, $level, false) >= $level;
}

function wfReload(string $gridId, string $message, string $resetForm = ''): array
{
    msgAdd($message, 'success');
    $js = '';
    foreach (array_filter(array_map('trim', explode(',', $gridId))) as $id) {
        $js .= "if (jqBiz('#{$id}').length) { try { jqBiz('#{$id}').datagrid('reload'); } catch (e) {} }";
    }
    if ($resetForm !== '') { $js .= " if (jqBiz('#{$resetForm}').length) { jqBiz('#{$resetForm}')[0].reset(); }"; }
    return ['content'=>['action'=>'eval', 'actionData'=>$js]];
}

function wfGo(string $route, string $message): array
{
    msgAdd($message, 'success');
    return ['content'=>['action'=>'href', 'link'=>BIZUNO_URL_PORTAL.'?bizRt='.$route]];
}

function wfClockDone(string $message): array
{
    msgAdd($message, 'success');
    $js = "if (jqBiz('#dgPeople').length) { jqBiz('#dgPeople').datagrid('reload'); }"
        ." if (jqBiz('#dgTime').length) { jqBiz('#dgTime').datagrid('reload'); }"
        ." if (jqBiz('#wfClock').length) { location.reload(); }";
    return ['content'=>['action'=>'eval', 'actionData'=>$js]];
}

function wfFail(string $message): array
{
    msgAdd($message, 'caution');
    return ['content'=>['action'=>'eval', 'actionData'=>'']];
}

function wfCoords(): array
{
    $data = str_replace(' ', '', (string)clean('data', 'text', 'get'));
    if (!preg_match('/^(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)$/', $data, $m)) { return [null, null]; }
    return [(float)$m[1], (float)$m[2]];
}

function wfOpenTime(int $userId): array
{
    $row = dbGetRow(wfTbl('time'), 'user_id='.(int)$userId.' AND end_time IS NULL');
    return is_array($row) ? $row : [];
}

function wfFenceCheck(?float $lat, ?float $lng): array
{
    $enabled = (string)wfSet('geo', 'enabled', '0') === '1';
    return wfInsideFence(
        $enabled,
        (float)wfSet('geo', 'latitude', '0'),
        (float)wfSet('geo', 'longitude', '0'),
        (float)wfSet('geo', 'radius', '0'),
        $lat,
        $lng
    );
}

function wfClockIn(int $userId, bool $enforceFence, ?float $lat, ?float $lng): array
{
    if (wfOpenTime($userId)) { return wfFail('That person is already clocked in.'); }
    if ($enforceFence) {
        $check = wfFenceCheck($lat, $lng);
        if (!$check['ok']) {
            if ($check['meters'] < 0) { return wfFail('Location is required to clock in. Allow location access and try again.'); }
            $away = (int)round($check['meters']);
            $radius = (int)wfSet('geo', 'radius', '0');
            return wfFail("You must be within {$radius} meters of the work location to clock in. You are about {$away} meters away.");
        }
    }
    dbWrite(wfTbl('time'), [
        'user_id'    => $userId,
        'start_time' => date('Y-m-d H:i:s'),
        'end_time'   => null,
        'duration'   => '00:00:00',
    ]);
    return wfClockDone('Clocked in.');
}

function wfClockOut(int $userId): array
{
    $open = wfOpenTime($userId);
    if (!$open) { return wfFail('Nobody is clocked in on that record.'); }
    $end = date('Y-m-d H:i:s');
    dbWrite(wfTbl('time'), [
        'end_time' => $end,
        'duration' => wfDuration((string)$open['start_time'], $end),
    ], 'update', 'id='.(int)$open['id']);
    return wfClockDone('Clocked out.');
}

function wfScope(string $ownSec, string $mgrSec): int
{
    $mine = (int)clean('mine', 'integer', 'get');
    if ($mine) {
        if (!wfGuard($ownSec, 1)) { return -1; }
        return wfUserId();
    }
    if (!wfGuard($mgrSec, 1)) { return -1; }
    return 0;
}

function wfPostedPeriod(): string
{
    $period = (string)clean('logPeriod', 'cmd', 'post');
    if ($period === '') { $period = (string)clean('logPeriod', 'cmd', 'get'); }
    return in_array($period, ['today', 'week', 'month'], true) ? $period : 'all';
}

function wfActor(string $ownSec, string $mgrSec, int $level): int
{
    $mine = (int)clean('mine', 'integer', 'get');
    if ($mine) {
        if (!wfGuard($ownSec, $level)) { return 0; }
        return wfUserId();
    }
    $posted = (int)clean('user_id', 'integer', 'post');
    if ($posted > 0 && $posted !== wfUserId()) {
        if (!wfGuard($mgrSec, $level)) { return 0; }
        return $posted;
    }
    if (!wfGuard($ownSec, $level, false) && !wfGuard($mgrSec, $level)) { return 0; }
    return $posted > 0 ? $posted : wfUserId();
}

function wfOwnRow(string $table, int $id): array
{
    if ($id < 1) { return []; }
    $row = dbGetRow(wfTbl($table), 'id='.$id);
    return is_array($row) ? $row : [];
}

function wfWhere(array $parts): string
{
    $parts = array_values(array_filter($parts, function ($p) { return $p !== ''; }));
    return implode(' AND ', $parts);
}

function wfWhitelist(): array
{
    $rows = dbGetMulti(wfTbl('allow'), '', 'email');
    return is_array($rows) ? $rows : [];
}

function wfWhitelistEmails(): array
{
    $out = [];
    foreach (wfWhitelist() as $row) { $out[] = (string)$row['email']; }
    return $out;
}

function wfGemini(string $prompt): string
{
    $key = (string)wfSet('ai', 'api_key', '');
    if ($key === '') { return ''; }
    $model = preg_replace('/[^a-zA-Z0-9._-]/', '', (string)wfSet('ai', 'model', 'gemini-flash-latest'));
    if ($model === '') { $model = 'gemini-flash-latest'; }
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';
    $body = json_encode(['contents'=>[['role'=>'user', 'parts'=>[['text'=>$prompt]]]]]);
    if (!function_exists('curl_init')) {
        msgAdd('PHP cURL is required to call Gemini.', 'caution');
        return '';
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: '.$key],
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        msgAdd('Gemini request failed'.($err !== '' ? ': '.$err : '')." (HTTP {$code}).", 'caution');
        return '';
    }
    $json = json_decode((string)$raw, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return is_string($text) ? $text : '';
}

function wfReportWindow(string $title, string $prompt): array
{
    if ((string)wfSet('ai', 'api_key', '') === '') {
        $html = '<p>Add a Gemini API key under Workforce settings before generating this report.</p>';
    } else {
        $text = wfGemini($prompt);
        $html = $text === '' ? '<p>Gemini did not return a report.</p>' : wfSafeHtml($text);
    }
    return ['type'=>'divHTML', 'title'=>$title, 'divs'=>[
        'body'=>['order'=>10, 'type'=>'html', 'html'=>'<div style="padding:12px">'.$html.'</div>'],
    ]];
}

function wfSendMail(string $to, string $name, string $subject, string $html): bool
{
    if (!class_exists(__NAMESPACE__.'\\bizunoMailer')) {
        bizAutoLoad('BIZUNO_FS_LIBRARY/model/mail.php', 'bizunoMailer');
    }
    if (!class_exists(__NAMESPACE__.'\\bizunoMailer')) {
        msgAdd('Bizuno mail is not available.', 'caution');
        return false;
    }
    $mail = new bizunoMailer($to, $name, $subject, $html);
    return (bool)$mail->sendMail();
}

function wfAct(string $icon, string $label, string $onClick, int $order = 50): array
{
    return ['order'=>$order, 'icon'=>$icon, 'label'=>$label, 'events'=>['onClick'=>$onClick]];
}

function wfGrid(string $id, string $url, array $columns, array $filters = []): array
{
    $grid = [
        'id'     => $id,
        'attr'   => ['idField'=>'id', 'toolbar'=>'#'.$id.'Toolbar', 'url'=>$url],
        'columns'=> $columns,
    ];
    if ($filters) { $grid['source']['filters'] = $filters; }
    return $grid;
}

function wfScreen(string $title, array $panels, array $extra = []): array
{
    $divs = [];
    $order = 10;
    foreach ($panels as $key => $panel) {
        if (empty($panel['label']) && !empty($panel['title'])) { $panels[$key]['label'] = $panel['title']; }
        $divs[$key] = ['order'=>$order, 'type'=>'panel', 'classes'=>[$panel['class'] ?? 'block50'], 'key'=>$key];
        $order += 10;
    }
    $data = [
        'type'  => 'divHTML',
        'title' => $title,
        'divs'  => [
            'intro'=> ['order'=>5,  'type'=>'html', 'html'=>'<h2>'.wfH($title).'</h2>'],
            'body' => ['order'=>20, 'type'=>'divs', 'classes'=>['areaView'], 'divs'=>$divs],
        ],
        'panels'=> $panels,
    ];
    return array_replace_recursive($data, $extra);
}
