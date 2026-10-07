<?php
/*
 * Dashboard: who is clocked in right now.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/dashboards/wf_onclock/wf_onclock.php
 */

namespace bizuno;

class wf_onclock
{
    public $moduleID = 'workforce';
    public $methodDir= 'dashboards';
    public $code     = 'wf_onclock';
    public $secID    = 'wf_people';
    public $category = 'workforce';
    public $struc;
    public $lang = [
        'title'       => 'Who Is Clocked In',
        'description' => 'Employees with an open time punch, and how long they have been in.',
    ];

    function __construct()
    {
        $this->struc = [
            'users' => ['order'=>10, 'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0]], 'admin'=>true],
            'roles' => ['order'=>20, 'label'=>lang('groups'), 'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true],
        ];
        metaPopulate($this->struc, getMetaDashboard($this->code) ?: []);
    }

    public function render($opts=[])
    {
        require_once dirname(__DIR__, 2).'/common.php';
        wfBoot();
        $rows = [];
        $open = dbGetMulti(wfTbl('time'), 'end_time IS NULL', 'start_time');
        foreach (is_array($open) ? $open : [] as $entry) {
            $left = wfH(wfUserName((int)$entry['user_id']));
            $right = wfH(wfDuration($entry['start_time'], null));
            $rows[] = viewDashLink($left, $right);
        }
        if (!$rows) { $rows[] = '<div><span>'.lang('no_results').'</span></div>'; }
        return ['lists'=>$rows];
    }
}
