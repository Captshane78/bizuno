<?php
/*
 * Team totals, Gemini reports, and CSV exports for a pay period.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/board.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';

class workforceBoard
{
    public $moduleID = 'workforce';
    public $pageID   = 'board';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_board', 1)) { return; }
        [$start, $end, $startDay, $endDay] = $this->bounds();
        $cards = '';
        foreach ($this->stats($start, $end) as $stat) {
            $cards .= '<div style="display:inline-block;vertical-align:top;margin:8px;padding:12px;border:1px solid #ccc;min-width:180px">'
                .'<strong>'.wfH($stat['name']).'</strong><br>Hours: '.wfH($stat['hours'])
                .'<br>Tasks completed: '.(int)$stat['tasks']
                .'<br>Work logs: '.(int)$stat['logs'].'</div>';
        }
        if ($cards === '') { $cards = '<p>No Bizuno users to report.</p>'; }
        $fields = [
            'wfStart' => ['order'=>10, 'break'=>false, 'label'=>'From', 'attr'=>['type'=>'text', 'value'=>$startDay]],
            'wfEnd'   => ['order'=>20, 'break'=>false, 'label'=>'To', 'attr'=>['type'=>'text', 'value'=>$endDay]],
            'btnGo'   => ['order'=>30, 'attr'=>['type'=>'button', 'value'=>'Refresh'], 'events'=>['onClick'=>'wfBoardReload();']],
            'btnPay'  => ['order'=>40, 'attr'=>['type'=>'button', 'value'=>'AI payroll report'], 'events'=>['onClick'=>"wfReport('payroll');"]],
            'btnTask' => ['order'=>50, 'attr'=>['type'=>'button', 'value'=>'AI task report'], 'events'=>['onClick'=>"wfReport('tasks');"]],
            'btnLog'  => ['order'=>60, 'attr'=>['type'=>'button', 'value'=>'AI work log report'], 'events'=>['onClick'=>"wfReport('logs');"]],
            'csvPay'  => ['order'=>70, 'attr'=>['type'=>'button', 'value'=>'Export payroll CSV'], 'events'=>['onClick'=>"wfCsv('payroll');"]],
            'csvTask' => ['order'=>80, 'attr'=>['type'=>'button', 'value'=>'Export tasks CSV'], 'events'=>['onClick'=>"wfCsv('tasks');"]],
            'csvLog'  => ['order'=>90, 'attr'=>['type'=>'button', 'value'=>'Export work logs CSV'], 'events'=>['onClick'=>"wfCsv('logs');"]],
            'csvKind' => ['order'=>10, 'attr'=>['type'=>'hidden']],
            'csvStart'=> ['order'=>20, 'attr'=>['type'=>'hidden']],
            'csvEnd'  => ['order'=>30, 'attr'=>['type'=>'hidden']],
        ];
        $panels = [
            'range' => ['title'=>'Payroll period', 'class'=>'block66', 'type'=>'divs', 'divs'=>[
                'dates' => ['order'=>10, 'type'=>'fields', 'keys'=>['wfStart', 'wfEnd', 'btnGo']],
                'ai'    => ['order'=>20, 'type'=>'fields', 'keys'=>['btnPay', 'btnTask', 'btnLog']],
                'csv'   => ['order'=>30, 'type'=>'fields', 'keys'=>['csvPay', 'csvTask', 'csvLog']],
                'formBOF'=> ['order'=>40, 'type'=>'form', 'key'=>'frmCsv'],
                'hidden'=> ['order'=>50, 'type'=>'fields', 'keys'=>['csvKind', 'csvStart', 'csvEnd']],
                'formEOF'=> ['order'=>60, 'type'=>'html', 'html'=>'</form>'],
            ]],
            'cards' => ['title'=>'Team', 'class'=>'block66', 'type'=>'html', 'html'=>$cards],
        ];
        $layout = array_replace_recursive($layout, wfScreen('Team Board', $panels, [
            'fields' => $fields,
            'forms'  => ['frmCsv'=>['attr'=>['type'=>'form', 'action'=>BIZUNO_URL_AJAX.'&bizRt=workforce/board/csv']]],
            'jsReady'=> ['init'=>"ajaxDownload('frmCsv');"],
            'jsHead' => ['board'=>"function wfVal(id){ var el = jqBiz('#'+id); if (el.data('textbox')) { return el.textbox('getValue'); } return el.val(); }
function wfBoardReload(){ hrefClick('workforce/board/manager&start='+encodeURIComponent(wfVal('wfStart'))+'&end='+encodeURIComponent(wfVal('wfEnd'))); }
function wfReport(kind){ windowEdit('workforce/board/report&kind='+kind+'&start='+encodeURIComponent(wfVal('wfStart'))+'&end='+encodeURIComponent(wfVal('wfEnd')), 'wfReport', 'Report', 900, 640); }
function wfCsv(kind){ jqBiz('#csvKind').val(kind); jqBiz('#csvStart').val(wfVal('wfStart')); jqBiz('#csvEnd').val(wfVal('wfEnd')); jqBiz('#frmCsv').submit(); }"],
        ]));
    }

    public function report(&$layout=[])
    {
        if (!wfGuard('wf_board', 1)) { return; }
        [$start, $end, $startDay, $endDay] = $this->bounds();
        $kind = (string)clean('kind', 'cmd', 'get');
        $bundle = $this->bundle($start, $end);
        if ($kind === 'tasks') {
            $prompt = wfPromptTasks($startDay, $endDay, $bundle['tasks']);
            $title = 'Task Performance Report';
        } elseif ($kind === 'logs') {
            $prompt = wfPromptLogs($startDay, $endDay, $bundle['logs']);
            $title = 'Work Log Report';
        } else {
            $prompt = wfPromptPayroll($startDay, $endDay, $bundle['payroll']);
            $title = 'Payroll Report';
        }
        $layout = array_replace_recursive($layout, wfReportWindow($title, $prompt));
    }

    public function csv(&$layout=[])
    {
        if (!wfGuard('wf_board', 1)) { return; }
        [$start, $end, $startDay, $endDay] = $this->bounds(true);
        $kind = (string)clean('csvKind', 'cmd', 'post');
        if ($kind === '') { $kind = (string)clean('kind', 'cmd', 'post'); }
        $names = [];
        foreach (wfUsers(false) as $user) { $names[(int)$user['id']] = wfContactName($user); }
        if ($kind === 'tasks') {
            $header = ['Employee Name', 'Task', 'Status', 'Created At'];
            $rows = [];
            $tasks = dbGetMulti(wfTbl('task'), "created_at>='$start' AND created_at<='$end'", 'created_at');
            foreach (is_array($tasks) ? $tasks : [] as $row) {
                $rows[] = [$names[(int)$row['user_id']] ?? 'Unknown', $row['task_text'], !empty($row['completed']) ? 'Completed' : 'Pending', $row['created_at']];
            }
            $file = 'task_report_'.$startDay.'_to_'.$endDay.'.csv';
        } elseif ($kind === 'logs') {
            $header = ['Employee Name', 'Activity', 'Part Number', 'Quantity', 'Duration', 'Date'];
            $rows = [];
            $logs = dbGetMulti(wfTbl('log'), "created_at>='$start' AND created_at<='$end'", 'created_at');
            foreach (is_array($logs) ? $logs : [] as $row) {
                $rows[] = [$names[(int)$row['user_id']] ?? 'Unknown', $row['log_text'], $row['part_number'] ?: 'N/A', $row['quantity'] ?: 'N/A', $row['duration'], $row['created_at']];
            }
            $file = 'work_log_report_'.$startDay.'_to_'.$endDay.'.csv';
        } else {
            $header = ['Employee Name', 'Date', 'Clock In', 'Clock Out', 'Duration (HH:MM:SS)'];
            $rows = [];
            $times = dbGetMulti(wfTbl('time'), "start_time>='$start' AND start_time<='$end'", 'start_time');
            foreach (is_array($times) ? $times : [] as $row) {
                $dur = empty($row['end_time']) ? wfDuration($row['start_time'], null) : $row['duration'];
                $rows[] = [$names[(int)$row['user_id']] ?? 'Unknown', substr($row['start_time'], 0, 10), $row['start_time'], $row['end_time'] ?: 'N/A', $dur];
            }
            $file = 'payroll_report_'.$startDay.'_to_'.$endDay.'.csv';
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$file.'"');
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>wfCsv($header, $rows)]);
    }

    private function bounds(bool $fromPost = false): array
    {
        $src = $fromPost ? 'post' : 'get';
        $startIn = (string)clean($fromPost ? 'csvStart' : 'start', 'text', $src);
        $endIn   = (string)clean($fromPost ? 'csvEnd' : 'end', 'text', $src);
        if ($startIn === '') { $startIn = date('Y-m-d', strtotime('-13 days')); }
        if ($endIn === '') { $endIn = date('Y-m-d'); }
        $start = wfDateBound($startIn, true);
        $end   = wfDateBound($endIn, false);
        if ($start === '') { $start = date('Y-m-d 00:00:00', strtotime('-13 days')); }
        if ($end === '') { $end = date('Y-m-d 23:59:59'); }
        return [$start, $end, substr($start, 0, 10), substr($end, 0, 10)];
    }

    private function stats(string $start, string $end): array
    {
        $seconds = [];
        $tasks = [];
        $logs = [];
        $timeRows = dbGetMulti(wfTbl('time'), "start_time>='$start' AND start_time<='$end'");
        foreach (is_array($timeRows) ? $timeRows : [] as $row) {
            $uid = (int)$row['user_id'];
            $chunk = empty($row['end_time']) ? wfSeconds(wfDuration($row['start_time'], null)) : wfSeconds((string)$row['duration']);
            $seconds[$uid] = ($seconds[$uid] ?? 0) + $chunk;
        }
        $taskRows = dbGetMulti(wfTbl('task'), "completed=1 AND created_at>='$start' AND created_at<='$end'", '', ['user_id']);
        foreach (is_array($taskRows) ? $taskRows : [] as $row) {
            $uid = (int)$row['user_id'];
            $tasks[$uid] = ($tasks[$uid] ?? 0) + 1;
        }
        $logRows = dbGetMulti(wfTbl('log'), "created_at>='$start' AND created_at<='$end'", '', ['user_id']);
        foreach (is_array($logRows) ? $logRows : [] as $row) {
            $uid = (int)$row['user_id'];
            $logs[$uid] = ($logs[$uid] ?? 0) + 1;
        }
        $out = [];
        foreach (wfUsers() as $user) {
            $id = (int)$user['id'];
            $out[] = [
                'name'  => wfContactName($user),
                'hours' => wfFormatHours($seconds[$id] ?? 0),
                'tasks' => $tasks[$id] ?? 0,
                'logs'  => $logs[$id] ?? 0,
            ];
        }
        return $out;
    }

    private function bundle(string $start, string $end): array
    {
        $byUser = [];
        foreach (wfUsers(false) as $user) {
            $byUser[(int)$user['id']] = ['name'=>wfContactName($user), 'seconds'=>0, 'tasks'=>[], 'logs'=>[]];
        }
        $timeRows = dbGetMulti(wfTbl('time'), "start_time>='$start' AND start_time<='$end'");
        foreach (is_array($timeRows) ? $timeRows : [] as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($byUser[$uid])) { continue; }
            $byUser[$uid]['seconds'] += empty($row['end_time']) ? wfSeconds(wfDuration($row['start_time'], null)) : wfSeconds((string)$row['duration']);
        }
        $taskRows = dbGetMulti(wfTbl('task'), "created_at>='$start' AND created_at<='$end'");
        foreach (is_array($taskRows) ? $taskRows : [] as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($byUser[$uid])) { continue; }
            $byUser[$uid]['tasks'][] = (!empty($row['completed']) ? '[COMPLETED] ' : '[PENDING] ').$row['task_text'];
        }
        $logRows = dbGetMulti(wfTbl('log'), "created_at>='$start' AND created_at<='$end'");
        foreach (is_array($logRows) ? $logRows : [] as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($byUser[$uid])) { continue; }
            $byUser[$uid]['logs'][] = '- '.$row['log_text'].' ('.$row['duration'].')';
        }
        $payroll = [];
        $taskText = [];
        $logText = [];
        foreach ($byUser as $user) {
            $done = 0;
            foreach ($user['tasks'] as $line) { if (str_starts_with($line, '[COMPLETED]')) { $done++; } }
            if ($user['seconds'] === 0 && !$user['tasks'] && !$user['logs']) { continue; }
            $hours = number_format($user['seconds'] / 3600, 2, '.', '');
            $payroll[] = "Employee: {$user['name']}\nTotal Hours Worked: {$hours}\nTasks Completed: {$done}\nWork Logs:\n".($user['logs'] ? implode("\n", $user['logs']) : '- none');
            if ($user['tasks']) {
                $pending = count($user['tasks']) - $done;
                $taskText[] = "Employee: {$user['name']}\nTotal Tasks: ".count($user['tasks'])."\nCompleted: {$done}\nPending: {$pending}\nTask Details:\n- ".implode("\n- ", $user['tasks']);
            }
            if ($user['logs']) {
                $logText[] = "Employee: {$user['name']}\nTotal Entries: ".count($user['logs'])."\nWork Log Details:\n".implode("\n", $user['logs']);
            }
        }
        return [
            'payroll' => $payroll ? implode("\n\n", $payroll) : 'No activity in this period.',
            'tasks'   => $taskText ? implode("\n\n", $taskText) : 'No tasks in this period.',
            'logs'    => $logText ? implode("\n\n", $logText) : 'No work logs in this period.',
        ];
    }
}
