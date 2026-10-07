<?php
/*
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/tasks.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';
require_once __DIR__.'/shift.php';

class workforceTasks
{
    public $moduleID = 'workforce';
    public $pageID   = 'tasks';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_tasks', 1)) { return; }
        $confirm = jsLang('msg_confirm_delete');
        $ajax = BIZUNO_URL_AJAX;
        $users = [];
        foreach (wfUsers() as $user) { $users[] = ['id'=>$user['id'], 'text'=>wfContactName($user)]; }
        $fields = [
            'user_id'   => ['order'=>10, 'label'=>'Employee', 'values'=>$users, 'attr'=>['type'=>'select']],
            'task_text' => ['order'=>20, 'label'=>'Task', 'attr'=>['type'=>'text']],
            'btnTask'   => ['order'=>30, 'attr'=>['type'=>'button', 'value'=>'Assign task'], 'events'=>['onClick'=>"jqBiz('#frmTaskMgr').submit();"]],
        ];
        $panels = [
            'add'  => ['title'=>'Assign a task', 'class'=>'block33', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmTaskMgr'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['user_id', 'task_text', 'btnTask']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
            ]],
            'grid' => ['title'=>'Tasks', 'class'=>'block66', 'type'=>'datagrid', 'key'=>'dgTasks'],
        ];
        $layout = array_replace_recursive($layout, wfScreen('Tasks', $panels, [
            'fields'   => $fields,
            'forms'    => ['frmTaskMgr'=>['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/tasks/save']]],
            'datagrid' => ['dgTasks'=>wfGrid('dgTasks', $ajax.'&bizRt=workforce/tasks/managerRows', wfTaskCols($confirm, false))],
            'jsReady'  => ['init'=>"ajaxForm('frmTaskMgr');"],
        ]));
    }

    public function managerRows(&$layout=[])
    {
        $user = wfScope('wf_shift', 'wf_tasks');
        if ($user < 0) { return; }
        $where = $user > 0 ? 'user_id='.$user : '';
        $rows = dbGetMulti(wfTbl('task'), $where, 'created_at DESC');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                'id'         => (int)$row['id'],
                'who'        => wfUserName((int)$row['user_id']),
                'task_text'  => $row['task_text'],
                'completed'  => !empty($row['completed']) ? 'Completed' : 'Pending',
                'created_at' => $row['created_at'],
            ];
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>count($out), 'rows'=>$out])]);
    }

    public function save(&$layout=[])
    {
        $user = wfActor('wf_shift', 'wf_tasks', 2);
        if ($user < 1) { return; }
        $text = trim((string)clean('task_text', 'text', 'post'));
        if ($text === '') { $layout = array_replace_recursive($layout, wfFail('Enter a task.')); return; }
        dbWrite(wfTbl('task'), [
            'user_id'    => $user,
            'task_text'  => $text,
            'completed'  => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $grid = (int)clean('mine', 'integer', 'get') ? 'dgShiftTasks' : 'dgTasks';
        $layout = array_replace_recursive($layout, wfReload($grid, 'Task added.', (int)clean('mine', 'integer', 'get') ? 'frmTask' : 'frmTaskMgr'));
    }

    public function toggle(&$layout=[])
    {
        $row = wfOwnRow('task', (int)clean('rID', 'integer', 'get'));
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Task not found.')); return; }
        $own = (int)$row['user_id'] === wfUserId();
        if ($own) { if (!wfGuard('wf_shift', 3, false) && !wfGuard('wf_tasks', 3)) { return; } }
        elseif (!wfGuard('wf_tasks', 3)) { return; }
        dbWrite(wfTbl('task'), ['completed'=>empty($row['completed']) ? 1 : 0], 'update', 'id='.(int)$row['id']);
        $layout = array_replace_recursive($layout, wfReload('dgShiftTasks,dgTasks', 'Task updated.'));
    }

    public function delete(&$layout=[])
    {
        $row = wfOwnRow('task', (int)clean('rID', 'integer', 'get'));
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Task not found.')); return; }
        $own = (int)$row['user_id'] === wfUserId();
        if ($own) { if (!wfGuard('wf_shift', 4, false) && !wfGuard('wf_tasks', 4)) { return; } }
        elseif (!wfGuard('wf_tasks', 4)) { return; }
        dbGetResult('DELETE FROM '.wfTbl('task').' WHERE id='.(int)$row['id']);
        $layout = array_replace_recursive($layout, wfReload('dgShiftTasks,dgTasks', 'Task deleted.'));
    }
}
