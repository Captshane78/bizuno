<?php
/*
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/logs.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';
require_once __DIR__.'/shift.php';

class workforceLogs
{
    public $moduleID = 'workforce';
    public $pageID   = 'logs';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_logs', 1)) { return; }
        $confirm = jsLang('msg_confirm_delete');
        $ajax = BIZUNO_URL_AJAX;
        $grid = wfGrid('dgLogs', $ajax.'&bizRt=workforce/logs/managerRows', wfLogCols($confirm, false), [
            'logPeriod'=>['order'=>10, 'label'=>'Period', 'values'=>wfPeriodValues(), 'attr'=>['type'=>'select', 'value'=>'all']],
        ]);
        $panels = ['grid'=>['title'=>'Work logs', 'class'=>'block66', 'type'=>'datagrid', 'key'=>'dgLogs']];
        $layout = array_replace_recursive($layout, wfScreen('Work Logs', $panels, [
            'datagrid'=>['dgLogs'=>$grid],
        ]));
    }

    public function managerRows(&$layout=[])
    {
        $user = wfScope('wf_shift', 'wf_logs');
        if ($user < 0) { return; }
        $parts = [];
        if ($user > 0) { $parts[] = 'user_id='.$user; }
        $since = wfPeriodStart(wfPostedPeriod());
        if ($since) { $parts[] = "created_at>='".addslashes($since)."'"; }
        $rows = dbGetMulti(wfTbl('log'), wfWhere($parts), 'created_at DESC');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = wfShow([
                'id'          => (int)$row['id'],
                'who'         => wfUserName((int)$row['user_id']),
                'log_text'    => $row['log_text'],
                'part_number' => $row['part_number'],
                'quantity'    => $row['quantity'],
                'duration'    => $row['duration'],
                'created_at'  => $row['created_at'],
            ]);
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>count($out), 'rows'=>$out])]);
    }

    public function save(&$layout=[])
    {
        $user = wfActor('wf_shift', 'wf_logs', 2);
        if ($user < 1) { return; }
        $text = trim((string)clean('log_text', 'text', 'post'));
        $duration = trim((string)clean('log_duration', 'text', 'post'));
        if ($text === '' || $duration === '') {
            $layout = array_replace_recursive($layout, wfFail('Describe the work and how long it took.'));
            return;
        }
        dbWrite(wfTbl('log'), [
            'user_id'     => $user,
            'log_text'    => $text,
            'part_number' => trim((string)clean('part_number', 'text', 'post')),
            'quantity'    => trim((string)clean('quantity', 'text', 'post')),
            'duration'    => $duration,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $grid = (int)clean('mine', 'integer', 'get') ? 'dgShiftLogs' : 'dgLogs';
        $layout = array_replace_recursive($layout, wfReload($grid, 'Work log added.', (int)clean('mine', 'integer', 'get') ? 'frmLog' : ''));
    }

    public function delete(&$layout=[])
    {
        $row = wfOwnRow('log', (int)clean('rID', 'integer', 'get'));
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Work log not found.')); return; }
        $own = (int)$row['user_id'] === wfUserId();
        if ($own) { if (!wfGuard('wf_shift', 4, false) && !wfGuard('wf_logs', 4)) { return; } }
        elseif (!wfGuard('wf_logs', 4)) { return; }
        dbGetResult('DELETE FROM '.wfTbl('log').' WHERE id='.(int)$row['id']);
        $layout = array_replace_recursive($layout, wfReload('dgShiftLogs,dgLogs', 'Work log deleted.'));
    }
}
