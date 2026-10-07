<?php
/*
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/calendar.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';
require_once __DIR__.'/shift.php';

class workforceCalendar
{
    public $moduleID = 'workforce';
    public $pageID   = 'calendar';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_calendar', 1)) { return; }
        $ajax = BIZUNO_URL_AJAX;
        $fields = [
            'event_date' => ['order'=>10, 'label'=>'Date', 'attr'=>['type'=>'date', 'value'=>biz_date()]],
            'event_name' => ['order'=>20, 'label'=>'Name', 'attr'=>['type'=>'text']],
            'event_type' => ['order'=>30, 'label'=>'Type', 'values'=>[['id'=>'holiday','text'=>'Holiday'], ['id'=>'event','text'=>'Event']], 'attr'=>['type'=>'select', 'value'=>'event']],
            'btnEvent'   => ['order'=>40, 'attr'=>['type'=>'button', 'value'=>'Add'], 'events'=>['onClick'=>"jqBiz('#frmEvent').submit();"]],
            'btnSeed'    => ['order'=>50, 'attr'=>['type'=>'button', 'value'=>'Add standard holidays'], 'events'=>['onClick'=>"jsonAction('workforce/calendar/seed');"]],
        ];
        $panels = [
            'add'  => ['title'=>'Add a day', 'class'=>'block33', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmEvent'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['event_date', 'event_name', 'event_type', 'btnEvent', 'btnSeed']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
            ]],
            'grid' => ['title'=>'Calendar', 'class'=>'block66', 'type'=>'datagrid', 'key'=>'dgCal'],
        ];
        $layout = array_replace_recursive($layout, wfScreen('Calendar', $panels, [
            'fields'   => $fields,
            'forms'    => ['frmEvent'=>['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/calendar/save']]],
            'datagrid' => ['dgCal'=>wfGrid('dgCal', $ajax.'&bizRt=workforce/calendar/managerRows', wfEventCols(true))],
            'jsReady'  => ['init'=>"ajaxForm('frmEvent');"],
        ]));
    }

    public function managerRows(&$layout=[])
    {
        $mine = (int)clean('mine', 'integer', 'get');
        if ($mine) {
            if (!wfCan('wf_shift', 1) && !wfCan('wf_calendar', 1)) {
                msgAdd(lang('err_no_permission').' [wf_shift]');
                return;
            }
        } elseif (!wfGuard('wf_calendar', 1)) {
            return;
        }
        $rows = dbGetMulti(wfTbl('event'), '', 'event_date');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = wfShow([
                'id'         => (int)$row['id'],
                'event_date' => $row['event_date'],
                'event_name' => $row['event_name'],
                'event_type' => $row['event_type'],
            ]);
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>count($out), 'rows'=>$out])]);
    }

    public function save(&$layout=[])
    {
        if (!wfGuard('wf_calendar', 2)) { return; }
        $name = trim((string)clean('event_name', 'text', 'post'));
        $date = (string)clean('event_date', 'date', 'post');
        $type = (string)clean('event_type', 'cmd', 'post');
        if (!in_array($type, ['holiday', 'event'], true)) { $type = 'event'; }
        if ($name === '' || $date === '' || $date === 'null') {
            $layout = array_replace_recursive($layout, wfFail('Date and name are required.'));
            return;
        }
        $this->addEvent($date, $name, $type);
        $layout = array_replace_recursive($layout, wfReload('dgCal,dgShiftCal', 'Added to the calendar.', 'frmEvent'));
    }

    public function delete(&$layout=[])
    {
        if (!wfGuard('wf_calendar', 4)) { return; }
        $id = (int)clean('rID', 'integer', 'get');
        if ($id < 1) { return; }
        dbGetResult('DELETE FROM '.wfTbl('event').' WHERE id='.$id);
        $layout = array_replace_recursive($layout, wfReload('dgCal,dgShiftCal', 'Removed from the calendar.'));
    }

    public function seed(&$layout=[])
    {
        if (!wfGuard('wf_calendar', 2)) { return; }
        $existing = dbGetMulti(wfTbl('event'), '', '', ['event_date', 'event_name']);
        $have = [];
        foreach (is_array($existing) ? $existing : [] as $row) { $have[$row['event_date'].'|'.$row['event_name']] = true; }
        $added = 0;
        foreach (wfHolidays() as $holiday) {
            $key = $holiday['date'].'|'.$holiday['name'];
            if (isset($have[$key])) { continue; }
            $this->addEvent($holiday['date'], $holiday['name'], $holiday['type']);
            $added++;
        }
        $layout = array_replace_recursive($layout, wfReload('dgCal', $added.' holidays added.'));
    }

    private function addEvent(string $date, string $name, string $type): void
    {
        dbWrite(wfTbl('event'), [
            'event_date' => $date,
            'event_name' => $name,
            'event_type' => $type,
            'created_by' => wfUserId(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
