<?php
/*
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/leave.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';
require_once __DIR__.'/shift.php';

class workforceLeave
{
    public $moduleID = 'workforce';
    public $pageID   = 'leave';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_leave', 1)) { return; }
        $ajax = BIZUNO_URL_AJAX;
        $panels = ['grid'=>['title'=>'Leave requests', 'class'=>'block66', 'type'=>'datagrid', 'key'=>'dgLeave']];
        $layout = array_replace_recursive($layout, wfScreen('Leave', $panels, [
            'datagrid'=>['dgLeave'=>wfGrid('dgLeave', $ajax.'&bizRt=workforce/leave/managerRows', wfLeaveCols(true))],
        ]));
    }

    public function managerRows(&$layout=[])
    {
        $user = wfScope('wf_shift', 'wf_leave');
        if ($user < 0) { return; }
        $where = $user > 0 ? 'user_id='.$user : '';
        $rows = dbGetMulti(wfTbl('leave'), $where, 'requested_at DESC');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                'id'           => (int)$row['id'],
                'who'          => wfUserName((int)$row['user_id']),
                'leave_type'   => $row['leave_type'],
                'start_date'   => $row['start_date'],
                'end_date'     => $row['end_date'],
                'reason'       => $row['reason'],
                'status'       => $row['status'],
                'requested_at' => $row['requested_at'],
            ];
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>count($out), 'rows'=>$out])]);
    }

    public function save(&$layout=[])
    {
        $user = wfActor('wf_shift', 'wf_leave', 2);
        if ($user < 1) { return; }
        $type = (string)clean('leave_type', 'cmd', 'post');
        if (!in_array($type, ['vacation', 'sick', 'time-off'], true)) { $type = 'time-off'; }
        $start = (string)clean('start_date', 'date', 'post');
        $end   = (string)clean('end_date', 'date', 'post');
        $reason = trim((string)clean('leave_reason', 'text', 'post'));
        if ($reason === '') { $reason = trim((string)clean('reason', 'text', 'post')); }
        if ($start === '' || $start === 'null' || $end === '' || $end === 'null' || $reason === '') {
            $layout = array_replace_recursive($layout, wfFail('Start, end, and a reason are required.'));
            return;
        }
        if ($end < $start) { $layout = array_replace_recursive($layout, wfFail('The end date is before the start date.')); return; }
        dbWrite(wfTbl('leave'), [
            'user_id'      => $user,
            'leave_type'   => $type,
            'start_date'   => $start,
            'end_date'     => $end,
            'reason'       => $reason,
            'status'       => 'pending',
            'requested_at' => date('Y-m-d H:i:s'),
            'handled_at'   => null,
            'handled_by'   => '',
        ]);
        $grid = (int)clean('mine', 'integer', 'get') ? 'dgShiftLeave' : 'dgLeave';
        $layout = array_replace_recursive($layout, wfReload($grid, 'Leave request submitted.', (int)clean('mine', 'integer', 'get') ? 'frmLeave' : ''));
    }

    public function decide(&$layout=[])
    {
        if (!wfGuard('wf_leave', 3)) { return; }
        $row = wfOwnRow('leave', (int)clean('rID', 'integer', 'get'));
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Leave request not found.')); return; }
        $status = (string)clean('data', 'cmd', 'get');
        if (!in_array($status, ['approved', 'rejected'], true)) {
            $layout = array_replace_recursive($layout, wfFail('Choose approved or rejected.'));
            return;
        }
        dbWrite(wfTbl('leave'), [
            'status'     => $status,
            'handled_at' => date('Y-m-d H:i:s'),
            'handled_by' => wfUserName(),
        ], 'update', 'id='.(int)$row['id']);
        $layout = array_replace_recursive($layout, wfReload('dgLeave,dgShiftLeave', 'Leave request '.$status.'.'));
    }
}
