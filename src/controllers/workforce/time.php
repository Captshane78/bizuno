<?php
/*
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/time.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';

class workforceTime
{
    public $moduleID = 'workforce';
    public $pageID   = 'time';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_time', 1)) { return; }
        $ajax = BIZUNO_URL_AJAX;
        $confirm = jsLang('msg_confirm_delete');
        $users = [];
        foreach (wfUsers() as $user) { $users[] = ['id'=>$user['id'], 'text'=>wfContactName($user)]; }
        $fields = [
            'user_id'    => ['order'=>10, 'label'=>'Employee', 'values'=>$users, 'attr'=>['type'=>'select']],
            'start_time' => ['order'=>20, 'label'=>'Clock in (YYYY-MM-DD HH:MM)', 'attr'=>['type'=>'text']],
            'end_time'   => ['order'=>30, 'label'=>'Clock out (blank if still in)', 'attr'=>['type'=>'text']],
            'btnAdd'     => ['order'=>40, 'attr'=>['type'=>'button', 'value'=>'Add entry'], 'events'=>['onClick'=>"jqBiz('#frmTime').submit();"]],
        ];
        $cols = wfWithTimeCols($confirm);
        $panels = [
            'add'  => ['title'=>'Add or correct a punch', 'class'=>'block33', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmTime'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['user_id', 'start_time', 'end_time', 'btnAdd']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
            ]],
            'grid' => ['title'=>'Time entries', 'class'=>'block66', 'type'=>'datagrid', 'key'=>'dgTime'],
        ];
        $layout = array_replace_recursive($layout, wfScreen('Time Entries', $panels, [
            'fields'   => $fields,
            'forms'    => ['frmTime'=>['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/time/save']]],
            'datagrid' => ['dgTime'=>wfGrid('dgTime', $ajax.'&bizRt=workforce/time/managerRows', $cols)],
            'jsReady'  => ['init'=>"ajaxForm('frmTime');"],
        ]));
    }

    public function managerRows(&$layout=[])
    {
        if (!wfGuard('wf_time', 1)) { return; }
        $rows = dbGetMulti(wfTbl('time'), '', 'start_time DESC', '*', 500);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $open = empty($row['end_time']);
            $out[] = [
                'id'         => (int)$row['id'],
                'who'        => wfUserName((int)$row['user_id']),
                'start_time' => $row['start_time'],
                'end_time'   => $open ? '' : $row['end_time'],
                'duration'   => $open ? wfDuration($row['start_time'], null) : $row['duration'],
                'state'      => $open ? 'In' : 'Out',
            ];
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>count($out), 'rows'=>$out])]);
    }

    public function save(&$layout=[])
    {
        if (!wfGuard('wf_time', 2)) { return; }
        $user = (int)clean('user_id', 'integer', 'post');
        $start = wfDateTime((string)clean('start_time', 'text', 'post'));
        $endRaw = trim((string)clean('end_time', 'text', 'post'));
        $end = $endRaw === '' ? '' : wfDateTime($endRaw);
        if ($user < 1 || $start === '') {
            $layout = array_replace_recursive($layout, wfFail('Choose an employee and a clock-in time.'));
            return;
        }
        if ($end !== '' && $end < $start) {
            $layout = array_replace_recursive($layout, wfFail('Clock out is before clock in.'));
            return;
        }
        if ($end === '' && wfOpenTime($user)) {
            $layout = array_replace_recursive($layout, wfFail('That person already has an open punch.'));
            return;
        }
        dbWrite(wfTbl('time'), [
            'user_id'    => $user,
            'start_time' => $start,
            'end_time'   => $end === '' ? null : $end,
            'duration'   => $end === '' ? '00:00:00' : wfDuration($start, $end),
        ]);
        $layout = array_replace_recursive($layout, wfReload('dgTime', 'Time entry added.', 'frmTime'));
    }

    public function edit(&$layout=[])
    {
        if (!wfGuard('wf_time', 3)) { return; }
        $row = wfOwnRow('time', (int)clean('rID', 'integer', 'get'));
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Time entry not found.')); return; }
        $id = (int)$row['id'];
        $fields = [
            'start_time' => ['order'=>10, 'label'=>'Clock in', 'attr'=>['type'=>'text', 'value'=>$row['start_time']]],
            'end_time'   => ['order'=>20, 'label'=>'Clock out', 'attr'=>['type'=>'text', 'value'=>$row['end_time'] ?? '']],
            'btn'        => ['order'=>30, 'attr'=>['type'=>'button', 'value'=>'Save'], 'events'=>['onClick'=>"jqBiz('#frmTimeEdit').submit();"]],
        ];
        $layout = array_replace_recursive($layout, [
            'type'   => 'divHTML',
            'divs'   => ['body'=>['order'=>10, 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmTimeEdit'],
                'fields' => ['order'=>20, 'type'=>'fields', 'keys'=>['start_time', 'end_time', 'btn']],
                'note'   => ['order'=>30, 'type'=>'html', 'html'=>'<p>'.wfH(wfUserName((int)$row['user_id'])).'</p>'],
                'formEOF'=> ['order'=>40, 'type'=>'html', 'html'=>'</form>'],
            ]]],
            'forms'  => ['frmTimeEdit'=>['attr'=>['type'=>'form', 'action'=>BIZUNO_URL_AJAX.'&bizRt=workforce/time/update&rID='.$id]]],
            'fields' => $fields,
            'jsReady'=> ['init'=>"ajaxForm('frmTimeEdit');"],
        ]);
    }

    public function update(&$layout=[])
    {
        if (!wfGuard('wf_time', 3)) { return; }
        $row = wfOwnRow('time', (int)clean('rID', 'integer', 'get'));
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Time entry not found.')); return; }
        $start = wfDateTime((string)clean('start_time', 'text', 'post'));
        $endRaw = trim((string)clean('end_time', 'text', 'post'));
        $end = $endRaw === '' ? '' : wfDateTime($endRaw);
        if ($start === '' || ($end !== '' && $end < $start)) {
            $layout = array_replace_recursive($layout, wfFail('Check the clock-in and clock-out times.'));
            return;
        }
        dbWrite(wfTbl('time'), [
            'start_time' => $start,
            'end_time'   => $end === '' ? null : $end,
            'duration'   => $end === '' ? '00:00:00' : wfDuration($start, $end),
        ], 'update', 'id='.(int)$row['id']);
        msgAdd('Time entry updated.', 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"jqBiz('#dgTime').datagrid('reload'); jqBiz('#wfTimeEdit').window('close');"]]);
    }

    public function delete(&$layout=[])
    {
        if (!wfGuard('wf_time', 4)) { return; }
        $id = (int)clean('rID', 'integer', 'get');
        if ($id < 1) { return; }
        dbGetResult('DELETE FROM '.wfTbl('time').' WHERE id='.$id);
        $layout = array_replace_recursive($layout, wfReload('dgTime', 'Time entry deleted.'));
    }

    public function clockIn(&$layout=[])
    {
        if (!wfCan('wf_time', 2) && !wfCan('wf_people', 2)) { msgAdd(lang('err_no_permission').' [wf_time]'); return; }
        $user = (int)clean('rID', 'integer', 'get');
        if ($user < 1) { $user = wfUserId(); }
        $layout = array_replace_recursive($layout, wfClockIn($user, false, null, null));
    }

    public function clockOut(&$layout=[])
    {
        if (!wfCan('wf_time', 2) && !wfCan('wf_people', 2)) { msgAdd(lang('err_no_permission').' [wf_time]'); return; }
        $user = (int)clean('rID', 'integer', 'get');
        if ($user < 1) { $user = wfUserId(); }
        $layout = array_replace_recursive($layout, wfClockOut($user));
    }
}

function wfWithTimeCols(string $confirm): array
{
    return [
        'action'     => ['order'=>1, 'label'=>lang('action'), 'attr'=>['width'=>80], 'actions'=>[
            'edit'  => wfAct('edit', lang('edit'), "windowEdit('workforce/time/edit&rID=idTBD', 'wfTimeEdit', 'Edit time', 480, 280);", 20),
            'trash' => wfAct('trash', lang('delete'), "if (confirm('$confirm')) jsonAction('workforce/time/delete', 'idTBD');", 80),
        ]],
        'who'        => ['order'=>10, 'label'=>'Employee', 'attr'=>['width'=>160]],
        'state'      => ['order'=>20, 'label'=>'Status', 'attr'=>['width'=>70]],
        'start_time' => ['order'=>30, 'label'=>'Clock in', 'attr'=>['width'=>150]],
        'end_time'   => ['order'=>40, 'label'=>'Clock out', 'attr'=>['width'=>150]],
        'duration'   => ['order'=>50, 'label'=>'Duration', 'attr'=>['width'=>90]],
    ];
}
