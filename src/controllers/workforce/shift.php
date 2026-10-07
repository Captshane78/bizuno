<?php
/*
 * Employee floor: clock, tasks, work log, supplies, leave, calendar, daily summary.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/shift.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';

class workforceShift
{
    public $moduleID = 'workforce';
    public $pageID   = 'shift';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_shift', 1)) { return; }
        $uid   = wfUserId();
        $open  = wfOpenTime($uid);
        $start = !empty($open['start_time']) ? (int)strtotime($open['start_time']) : 0;
        $status = $open
            ? '<p style="color:#2a2;font-weight:bold">Clocked in since '.wfH($open['start_time']).'</p><p id="wfElapsed" style="font-size:1.6em"></p>'
            : '<p style="color:#a22;font-weight:bold">Clocked out</p><p id="wfElapsed" style="font-size:1.6em">00:00:00</p>';
        $geo = [
            'enabled'   => (string)wfSet('geo', 'enabled', '0') === '1',
            'latitude'  => (float)wfSet('geo', 'latitude', '0'),
            'longitude' => (float)wfSet('geo', 'longitude', '0'),
            'radius'    => (float)wfSet('geo', 'radius', '0'),
        ];
        $confirm = jsLang('msg_confirm_delete');
        $ajax = BIZUNO_URL_AJAX;
        $fields = [
            'task_text'  => ['order'=>10, 'label'=>'Task', 'attr'=>['type'=>'text']],
            'btnTask'    => ['order'=>20, 'attr'=>['type'=>'button', 'value'=>'Add task'], 'events'=>['onClick'=>"jqBiz('#frmTask').submit();"]],
            'log_text'   => ['order'=>10, 'label'=>'What did you build or do?', 'attr'=>['type'=>'text']],
            'part_number'=> ['order'=>20, 'label'=>'Part number', 'attr'=>['type'=>'text']],
            'quantity'   => ['order'=>30, 'label'=>'Quantity', 'attr'=>['type'=>'text']],
            'log_duration'=> ['order'=>40, 'label'=>'Time spent', 'attr'=>['type'=>'text']],
            'btnLog'     => ['order'=>50, 'attr'=>['type'=>'button', 'value'=>'Add work log'], 'events'=>['onClick'=>"jqBiz('#frmLog').submit();"]],
            'part'       => ['order'=>10, 'label'=>'Part', 'attr'=>['type'=>'text']],
            'sup_qty'    => ['order'=>20, 'label'=>'Quantity', 'attr'=>['type'=>'text']],
            'reason'     => ['order'=>30, 'label'=>'Reason', 'attr'=>['type'=>'textarea']],
            'btnSupply'  => ['order'=>40, 'attr'=>['type'=>'button', 'value'=>'Request supply'], 'events'=>['onClick'=>"jqBiz('#frmSupply').submit();"]],
            'leave_type' => ['order'=>10, 'label'=>'Type', 'values'=>[['id'=>'vacation','text'=>'Vacation'], ['id'=>'sick','text'=>'Sick'], ['id'=>'time-off','text'=>'Time off']], 'attr'=>['type'=>'select', 'value'=>'vacation']],
            'start_date' => ['order'=>20, 'label'=>'Start', 'attr'=>['type'=>'date', 'value'=>biz_date()]],
            'end_date'   => ['order'=>30, 'label'=>'End', 'attr'=>['type'=>'date', 'value'=>biz_date()]],
            'leave_reason'=> ['order'=>40, 'label'=>'Reason', 'attr'=>['type'=>'textarea']],
            'btnLeave'   => ['order'=>50, 'attr'=>['type'=>'button', 'value'=>'Request leave'], 'events'=>['onClick'=>"jqBiz('#frmLeave').submit();"]],
            'btnIn'      => ['order'=>10, 'attr'=>['type'=>'button', 'value'=>'Clock in'], 'events'=>['onClick'=>'wfClockIn();']],
            'btnOut'     => ['order'=>20, 'attr'=>['type'=>'button', 'value'=>'Clock out'], 'events'=>['onClick'=>"jsonAction('workforce/shift/clockOut');"]],
            'btnSummary' => ['order'=>30, 'attr'=>['type'=>'button', 'value'=>'Daily summary'], 'events'=>['onClick'=>"windowEdit('workforce/shift/summary', 'wfSummary', 'Daily Work Summary', 720, 520);"]],
        ];
        $panels = [
            'clock' => ['title'=>'Time clock', 'class'=>'block33', 'type'=>'divs', 'divs'=>[
                'status'=> ['order'=>10, 'type'=>'html', 'html'=>'<div id="wfClock">'.$status.'</div>'],
                'btns'  => ['order'=>20, 'type'=>'fields', 'keys'=>['btnIn', 'btnOut', 'btnSummary']],
            ]],
            'tasks' => ['title'=>'Tasks', 'class'=>'block50', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmTask'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['task_text', 'btnTask']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
                'grid'   => ['order'=>40, 'type'=>'datagrid', 'key'=>'dgShiftTasks'],
            ]],
            'logs' => ['title'=>'Work log', 'class'=>'block50', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmLog'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['log_text', 'part_number', 'quantity', 'log_duration', 'btnLog']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
                'grid'   => ['order'=>40, 'type'=>'datagrid', 'key'=>'dgShiftLogs'],
            ]],
            'supply' => ['title'=>'Supplies', 'class'=>'block50', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmSupply'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['part', 'sup_qty', 'reason', 'btnSupply']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
                'grid'   => ['order'=>40, 'type'=>'datagrid', 'key'=>'dgShiftSupply'],
            ]],
            'leave' => ['title'=>'Leave', 'class'=>'block50', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmLeave'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['leave_type', 'start_date', 'end_date', 'leave_reason', 'btnLeave']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
                'grid'   => ['order'=>40, 'type'=>'datagrid', 'key'=>'dgShiftLeave'],
            ]],
            'calendar' => ['title'=>'Company calendar', 'class'=>'block33', 'type'=>'datagrid', 'key'=>'dgShiftCal'],
        ];
        $data = wfScreen('My Shift', $panels, [
            'fields'  => $fields,
            'forms'   => [
                'frmTask'   => ['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/tasks/save&mine=1']],
                'frmLog'    => ['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/logs/save&mine=1']],
                'frmSupply' => ['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/supply/save&mine=1']],
                'frmLeave'  => ['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/leave/save&mine=1']],
            ],
            'datagrid'=> [
                'dgShiftTasks'  => wfGrid('dgShiftTasks', $ajax.'&bizRt=workforce/tasks/managerRows&mine=1', wfTaskCols($confirm, true)),
                'dgShiftLogs'   => wfGrid('dgShiftLogs', $ajax.'&bizRt=workforce/logs/managerRows&mine=1', wfLogCols($confirm, true), [
                    'logPeriod'=>['order'=>10, 'label'=>'Period', 'values'=>wfPeriodValues(), 'attr'=>['type'=>'select', 'value'=>'all']],
                ]),
                'dgShiftSupply' => wfGrid('dgShiftSupply', $ajax.'&bizRt=workforce/supply/managerRows&mine=1', wfSupplyCols(false)),
                'dgShiftLeave'  => wfGrid('dgShiftLeave', $ajax.'&bizRt=workforce/leave/managerRows&mine=1', wfLeaveCols(false)),
                'dgShiftCal'    => wfGrid('dgShiftCal', $ajax.'&bizRt=workforce/calendar/managerRows&mine=1', wfEventCols(false)),
            ],
            'jsReady' => ['init'=>"ajaxForm('frmTask'); ajaxForm('frmLog'); ajaxForm('frmSupply'); ajaxForm('frmLeave'); wfTick(); setInterval(wfTick, 1000);"],
            'jsHead'  => ['clock'=>wfClockJs($geo, $start)],
        ]);
        $layout = array_replace_recursive($layout, $data);
    }

    public function clockIn(&$layout=[])
    {
        if (!wfGuard('wf_shift', 2)) { return; }
        [$lat, $lng] = wfCoords();
        $layout = array_replace_recursive($layout, wfClockIn(wfUserId(), true, $lat, $lng));
    }

    public function clockOut(&$layout=[])
    {
        if (!wfGuard('wf_shift', 2)) { return; }
        $layout = array_replace_recursive($layout, wfClockOut(wfUserId()));
    }

    public function summary(&$layout=[])
    {
        if (!wfGuard('wf_shift', 1)) { return; }
        $uid   = wfUserId();
        $name  = wfUserName($uid);
        $start = date('Y-m-d 00:00:00');
        $end   = date('Y-m-d 23:59:59');
        $times = dbGetMulti(wfTbl('time'), "user_id=$uid AND start_time>='$start' AND start_time<='$end'", 'start_time');
        $tasks = dbGetMulti(wfTbl('task'), "user_id=$uid AND completed=1 AND created_at>='$start' AND created_at<='$end'", 'created_at');
        $logs  = dbGetMulti(wfTbl('log'), "user_id=$uid AND created_at>='$start' AND created_at<='$end'", 'created_at');
        $times = is_array($times) ? $times : [];
        $tasks = is_array($tasks) ? $tasks : [];
        $logs  = is_array($logs) ? $logs : [];
        if (!$times && !$tasks && !$logs) {
            $layout = array_replace_recursive($layout, ['type'=>'divHTML', 'divs'=>['body'=>['order'=>10, 'type'=>'html', 'html'=>'<p>Nothing recorded today yet.</p>']]]);
            return;
        }
        $seconds = 0;
        $timeLines = [];
        foreach ($times as $row) {
            $dur = empty($row['end_time']) ? wfDuration($row['start_time'], null) : (string)$row['duration'];
            $seconds += wfSeconds($dur);
            $timeLines[] = '- Clocked in at '.$row['start_time'].', clocked out at '.($row['end_time'] ?: 'still in').' (Duration: '.$dur.')';
        }
        $taskLines = [];
        foreach ($tasks as $row) { $taskLines[] = '- '.$row['task_text']; }
        $logLines = [];
        foreach ($logs as $row) { $logLines[] = '- '.$row['log_text'].' (Duration: '.$row['duration'].')'; }
        $prompt = wfPromptDaily(
            $name,
            'Total Work Duration: '.wfFormatHours($seconds).'.',
            $timeLines ? "Time Entries:\n".implode("\n", $timeLines) : 'No time entries recorded today.',
            $taskLines ? "Completed Tasks:\n".implode("\n", $taskLines) : 'No tasks were completed today.',
            $logLines ? "Work Logs (What was built/done):\n".implode("\n", $logLines) : 'No work logs recorded today.'
        );
        $layout = array_replace_recursive($layout, wfReportWindow('Daily Work Summary', $prompt));
    }
}

function wfClockJs(array $geo, int $started): string
{
    $json = json_encode($geo);
    return "var wfGeo = $json; var wfStarted = ".(int)$started.";
function wfMeters(lat1, lon1, lat2, lon2) {
  var R = 6371000, p1 = lat1 * Math.PI/180, p2 = lat2 * Math.PI/180, dp = (lat2-lat1) * Math.PI/180, dl = (lon2-lon1) * Math.PI/180;
  var a = Math.sin(dp/2) * Math.sin(dp/2) + Math.cos(p1) * Math.cos(p2) * Math.sin(dl/2) * Math.sin(dl/2);
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}
function wfClockIn() {
  if (!wfGeo.enabled) { jsonAction('workforce/shift/clockIn', '0', ''); return; }
  if (!navigator.geolocation) { alert('Geolocation is not supported by your browser. Cannot verify location.'); return; }
  navigator.geolocation.getCurrentPosition(function(pos) {
    var dist = wfMeters(pos.coords.latitude, pos.coords.longitude, wfGeo.latitude, wfGeo.longitude);
    if (dist > wfGeo.radius) {
      alert('You must be within ' + wfGeo.radius + ' meters of the work location to clock in. You are currently estimated to be ' + Math.round(dist) + ' meters away.');
      return;
    }
    jsonAction('workforce/shift/clockIn', '0', pos.coords.latitude + ',' + pos.coords.longitude);
  }, function() { alert('Could not get your location. Allow location access to clock in.'); }, {enableHighAccuracy:true, timeout:10000, maximumAge:0});
}
function wfTick() {
  var el = document.getElementById('wfElapsed');
  if (!el || !wfStarted) return;
  var s = Math.max(0, Math.floor(Date.now()/1000 - wfStarted));
  var h = String(Math.floor(s/3600)).padStart(2,'0'), m = String(Math.floor((s%3600)/60)).padStart(2,'0'), c = String(s%60).padStart(2,'0');
  el.textContent = h+':'+m+':'+c;
}";
}

function wfPeriodValues(): array
{
    return [
        ['id'=>'all', 'text'=>'All'],
        ['id'=>'today', 'text'=>'Today'],
        ['id'=>'week', 'text'=>'This week'],
        ['id'=>'month', 'text'=>'This month'],
    ];
}

function wfTaskCols(string $confirm, bool $mine): array
{
    $cols = [
        'who'        => ['order'=>10, 'label'=>'Employee', 'attr'=>['width'=>140, 'hidden'=>$mine]],
        'task_text'  => ['order'=>20, 'label'=>'Task', 'attr'=>['width'=>280]],
        'completed'  => ['order'=>30, 'label'=>'Status', 'attr'=>['width'=>90]],
        'created_at' => ['order'=>40, 'label'=>'Created', 'attr'=>['width'=>140]],
    ];
    return wfWithActions($cols, [
        'toggle'=> wfAct('apply', 'Toggle', "jsonAction('workforce/tasks/toggle', 'idTBD');", 20),
        'trash' => wfAct('trash', lang('delete'), "if (confirm('$confirm')) jsonAction('workforce/tasks/delete', 'idTBD');", 80),
    ]);
}

function wfLogCols(string $confirm, bool $mine): array
{
    $cols = [
        'who'         => ['order'=>10, 'label'=>'Employee', 'attr'=>['width'=>140, 'hidden'=>$mine]],
        'log_text'    => ['order'=>20, 'label'=>'Activity', 'attr'=>['width'=>240]],
        'part_number' => ['order'=>30, 'label'=>'Part', 'attr'=>['width'=>100]],
        'quantity'    => ['order'=>40, 'label'=>'Qty', 'attr'=>['width'=>70]],
        'duration'    => ['order'=>50, 'label'=>'Duration', 'attr'=>['width'=>90]],
        'created_at'  => ['order'=>60, 'label'=>'When', 'attr'=>['width'=>140]],
    ];
    return wfWithActions($cols, [
        'trash'=> wfAct('trash', lang('delete'), "if (confirm('$confirm')) jsonAction('workforce/logs/delete', 'idTBD');", 80),
    ]);
}

function wfSupplyCols(bool $manage): array
{
    $cols = [
        'who'          => ['order'=>10, 'label'=>'Employee', 'attr'=>['width'=>140, 'hidden'=>!$manage]],
        'part'         => ['order'=>20, 'label'=>'Part', 'attr'=>['width'=>140]],
        'quantity'     => ['order'=>30, 'label'=>'Qty', 'attr'=>['width'=>70]],
        'reason'       => ['order'=>40, 'label'=>'Reason', 'attr'=>['width'=>200]],
        'status'       => ['order'=>50, 'label'=>'Status', 'attr'=>['width'=>80]],
        'source'       => ['order'=>60, 'label'=>'Source', 'attr'=>['width'=>100, 'hidden'=>!$manage]],
        'requested_at' => ['order'=>70, 'label'=>'Requested', 'attr'=>['width'=>140]],
    ];
    $actions = [];
    if ($manage) {
        $actions['handle'] = wfAct('apply', 'Handled', "windowEdit('workforce/supply/handle&rID=idTBD', 'wfHandle', 'Mark handled', 480, 260);", 20);
    }
    return wfWithActions($cols, $actions);
}

function wfLeaveCols(bool $manage): array
{
    $cols = [
        'who'          => ['order'=>10, 'label'=>'Employee', 'attr'=>['width'=>140, 'hidden'=>!$manage]],
        'leave_type'   => ['order'=>20, 'label'=>'Type', 'attr'=>['width'=>90]],
        'start_date'   => ['order'=>30, 'label'=>'Start', 'attr'=>['width'=>100]],
        'end_date'     => ['order'=>40, 'label'=>'End', 'attr'=>['width'=>100]],
        'reason'       => ['order'=>50, 'label'=>'Reason', 'attr'=>['width'=>180]],
        'status'       => ['order'=>60, 'label'=>'Status', 'attr'=>['width'=>90]],
        'requested_at' => ['order'=>70, 'label'=>'Requested', 'attr'=>['width'=>140]],
    ];
    $actions = [];
    if ($manage) {
        $actions['approve'] = wfAct('apply', 'Approve', "jsonAction('workforce/leave/decide', 'idTBD', 'approved');", 20);
        $actions['reject']  = wfAct('cancel', 'Reject', "jsonAction('workforce/leave/decide', 'idTBD', 'rejected');", 30);
    }
    return wfWithActions($cols, $actions);
}

function wfEventCols(bool $manage): array
{
    $confirm = jsLang('msg_confirm_delete');
    $cols = [
        'event_date' => ['order'=>10, 'label'=>'Date', 'attr'=>['width'=>110]],
        'event_name' => ['order'=>20, 'label'=>'Name', 'attr'=>['width'=>220]],
        'event_type' => ['order'=>30, 'label'=>'Type', 'attr'=>['width'=>90]],
    ];
    $actions = [];
    if ($manage) {
        $actions['trash'] = wfAct('trash', lang('delete'), "if (confirm('$confirm')) jsonAction('workforce/calendar/delete', 'idTBD');", 80);
    }
    return wfWithActions($cols, $actions);
}

function wfWithActions(array $cols, array $actions): array
{
    if (!$actions) { return $cols; }
    return array_merge([
        'action' => ['order'=>1, 'label'=>lang('action'), 'attr'=>['width'=>80], 'actions'=>$actions],
    ], $cols);
}
