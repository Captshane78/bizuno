<?php
/*
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/supply.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';
require_once __DIR__.'/shift.php';

class workforceSupply
{
    public $moduleID = 'workforce';
    public $pageID   = 'supply';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_supply', 1)) { return; }
        $ajax = BIZUNO_URL_AJAX;
        $users = [];
        foreach (wfUsers() as $user) { $users[] = ['id'=>$user['id'], 'text'=>wfContactName($user)]; }
        $fields = [
            'user_id'  => ['order'=>10, 'label'=>'Employee', 'values'=>$users, 'attr'=>['type'=>'select', 'value'=>wfUserId()]],
            'part'     => ['order'=>20, 'label'=>'Part', 'attr'=>['type'=>'text']],
            'sup_qty'  => ['order'=>30, 'label'=>'Quantity', 'attr'=>['type'=>'text']],
            'reason'   => ['order'=>40, 'label'=>'Reason', 'attr'=>['type'=>'textarea']],
            'btnSupply'=> ['order'=>50, 'attr'=>['type'=>'button', 'value'=>'Add request'], 'events'=>['onClick'=>"jqBiz('#frmSupplyMgr').submit();"]],
        ];
        $panels = [
            'add'  => ['title'=>'New request', 'class'=>'block33', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmSupplyMgr'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['user_id', 'part', 'sup_qty', 'reason', 'btnSupply']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
            ]],
            'grid' => ['title'=>'Supply requests', 'class'=>'block66', 'type'=>'datagrid', 'key'=>'dgSupply'],
        ];
        $layout = array_replace_recursive($layout, wfScreen('Supplies', $panels, [
            'fields'   => $fields,
            'forms'    => ['frmSupplyMgr'=>['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/supply/save']]],
            'datagrid' => ['dgSupply'=>wfGrid('dgSupply', $ajax.'&bizRt=workforce/supply/managerRows', wfSupplyCols(true))],
            'jsReady'  => ['init'=>"ajaxForm('frmSupplyMgr');"],
        ]));
    }

    public function managerRows(&$layout=[])
    {
        $user = wfScope('wf_shift', 'wf_supply');
        if ($user < 0) { return; }
        $where = $user > 0 ? 'user_id='.$user : '';
        $rows = dbGetMulti(wfTbl('supply'), $where, 'requested_at DESC');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[] = [
                'id'           => (int)$row['id'],
                'who'          => wfUserName((int)$row['user_id']),
                'part'         => $row['part'],
                'quantity'     => $row['quantity'],
                'reason'       => $row['reason'],
                'status'       => $row['status'],
                'source'       => $row['source'],
                'requested_at' => $row['requested_at'],
            ];
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>count($out), 'rows'=>$out])]);
    }

    public function save(&$layout=[])
    {
        $user = wfActor('wf_shift', 'wf_supply', 2);
        if ($user < 1) { return; }
        $part = trim((string)clean('part', 'text', 'post'));
        $qty  = trim((string)clean('sup_qty', 'text', 'post'));
        $reason = trim((string)clean('reason', 'text', 'post'));
        if ($part === '' || $qty === '' || $reason === '') {
            $layout = array_replace_recursive($layout, wfFail('Part, quantity, and reason are required.'));
            return;
        }
        dbWrite(wfTbl('supply'), [
            'user_id'      => $user,
            'part'         => $part,
            'quantity'     => $qty,
            'reason'       => $reason,
            'status'       => 'pending',
            'source'       => '',
            'requested_at' => date('Y-m-d H:i:s'),
            'handled_at'   => null,
            'handled_by'   => '',
        ]);
        $grid = (int)clean('mine', 'integer', 'get') ? 'dgShiftSupply' : 'dgSupply';
        $form = (int)clean('mine', 'integer', 'get') ? 'frmSupply' : 'frmSupplyMgr';
        $layout = array_replace_recursive($layout, wfReload($grid, 'Request submitted.', $form));
    }

    public function handle(&$layout=[])
    {
        if (!wfGuard('wf_supply', 3)) { return; }
        $id = (int)clean('rID', 'integer', 'get');
        $row = wfOwnRow('supply', $id);
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Request not found.')); return; }
        $fields = [
            'source' => ['order'=>10, 'label'=>'Where it came from', 'attr'=>['type'=>'text', 'value'=>$row['source']]],
            'btn'    => ['order'=>20, 'attr'=>['type'=>'button', 'value'=>'Mark handled'], 'events'=>['onClick'=>"jqBiz('#frmHandle').submit();"]],
        ];
        $layout = array_replace_recursive($layout, [
            'type'   => 'divHTML',
            'divs'   => ['body'=>['order'=>10, 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmHandle'],
                'fields' => ['order'=>20, 'type'=>'fields', 'keys'=>['source', 'btn']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
            ]]],
            'forms'  => ['frmHandle'=>['attr'=>['type'=>'form', 'action'=>BIZUNO_URL_AJAX.'&bizRt=workforce/supply/handled&rID='.$id]]],
            'fields' => $fields,
            'jsReady'=> ['init'=>"ajaxForm('frmHandle');"],
        ]);
    }

    public function handled(&$layout=[])
    {
        if (!wfGuard('wf_supply', 3)) { return; }
        $id = (int)clean('rID', 'integer', 'get');
        $row = wfOwnRow('supply', $id);
        if (!$row) { $layout = array_replace_recursive($layout, wfFail('Request not found.')); return; }
        dbWrite(wfTbl('supply'), [
            'status'     => 'handled',
            'source'     => trim((string)clean('source', 'text', 'post')),
            'handled_at' => date('Y-m-d H:i:s'),
            'handled_by' => wfUserName(),
        ], 'update', 'id='.$id);
        msgAdd('Request marked handled.', 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"jqBiz('#dgSupply').datagrid('reload'); jqBiz('#wfHandle').window('close');"]]);
    }
}
