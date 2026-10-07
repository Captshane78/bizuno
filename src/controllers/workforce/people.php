<?php
/*
 * Who is here, welcome email, and the external-email whitelist.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/people.php
 */

namespace bizuno;

require_once __DIR__.'/common.php';

class workforcePeople
{
    public $moduleID = 'workforce';
    public $pageID   = 'people';

    function __construct()
    {
        wfBoot();
    }

    public function manager(&$layout=[])
    {
        if (!wfGuard('wf_people', 1)) { return; }
        $ajax = BIZUNO_URL_AJAX;
        $domain = wfH((string)wfSet('access', 'domain', 'teamdreamweaver.com'));
        $fields = [
            'email'   => ['order'=>10, 'label'=>'Email', 'attr'=>['type'=>'text']],
            'btnAllow'=> ['order'=>20, 'attr'=>['type'=>'button', 'value'=>'Add to whitelist'], 'events'=>['onClick'=>"jqBiz('#frmAllow').submit();"]],
        ];
        $cols = [
            'action' => ['order'=>1, 'label'=>lang('action'), 'attr'=>['width'=>120], 'actions'=>[
                'in'      => wfAct('checkin', 'Clock in', "jsonAction('workforce/time/clockIn', 'idTBD');", 10),
                'out'     => wfAct('checkout', 'Clock out', "jsonAction('workforce/time/clockOut', 'idTBD');", 20),
                'detail'  => wfAct('preview', 'Detail', "windowEdit('workforce/people/detail&rID=idTBD', 'wfDetail', 'Employee', 860, 640);", 30),
                'welcome' => wfAct('email', 'Welcome email', "jsonAction('workforce/people/welcome', 'idTBD');", 40),
            ]],
            'who'      => ['order'=>10, 'label'=>'Name', 'attr'=>['width'=>180]],
            'email'    => ['order'=>20, 'label'=>'Email', 'attr'=>['width'=>200]],
            'state'    => ['order'=>30, 'label'=>'Now', 'attr'=>['width'=>70]],
            'today'    => ['order'=>40, 'label'=>'Today', 'attr'=>['width'=>80]],
            'tasks'    => ['order'=>50, 'label'=>'Open tasks', 'attr'=>['width'=>90]],
            'leave'    => ['order'=>60, 'label'=>'Pending leave', 'attr'=>['width'=>110]],
            'supplies' => ['order'=>70, 'label'=>'Pending supplies', 'attr'=>['width'=>120]],
        ];
        $allowRows = '';
        foreach (wfWhitelist() as $row) {
            $allowRows .= '<li>'.wfH($row['email']).' <a href="#" onclick="jsonAction(\'workforce/people/unallow\', \''.(int)$row['id'].'\'); return false;">remove</a></li>';
        }
        if ($allowRows === '') { $allowRows = '<li>No extra emails yet.</li>'; }
        $note = '<p>People are Bizuno users. Add a login under <a href="#" onclick="hrefClick(\'contacts/main/manager&type=u\'); return false;">Administration → Users</a>, then send the welcome email from this list. '
            .'Invites are limited to @'.$domain.' and the whitelist below.</p><ul>'.$allowRows.'</ul>';
        $panels = [
            'note'  => ['title'=>'Accounts', 'class'=>'block33', 'type'=>'html', 'html'=>$note],
            'allow' => ['title'=>'Whitelist', 'class'=>'block33', 'type'=>'divs', 'divs'=>[
                'formBOF'=> ['order'=>10, 'type'=>'form', 'key'=>'frmAllow'],
                'body'   => ['order'=>20, 'type'=>'fields', 'keys'=>['email', 'btnAllow']],
                'formEOF'=> ['order'=>30, 'type'=>'html', 'html'=>'</form>'],
            ]],
            'grid'  => ['title'=>'On the floor', 'class'=>'block66', 'type'=>'datagrid', 'key'=>'dgPeople'],
        ];
        $layout = array_replace_recursive($layout, wfScreen('People', $panels, [
            'fields'   => $fields,
            'forms'    => ['frmAllow'=>['attr'=>['type'=>'form', 'action'=>$ajax.'&bizRt=workforce/people/allow']]],
            'datagrid' => ['dgPeople'=>wfGrid('dgPeople', $ajax.'&bizRt=workforce/people/managerRows', $cols)],
            'jsReady'  => ['init'=>"ajaxForm('frmAllow');"],
        ]));
    }

    public function managerRows(&$layout=[])
    {
        if (!wfGuard('wf_people', 1)) { return; }
        $today = date('Y-m-d 00:00:00');
        $open = [];
        $seconds = [];
        $times = dbGetMulti(wfTbl('time'), "start_time>='$today'");
        foreach (is_array($times) ? $times : [] as $row) {
            $uid = (int)$row['user_id'];
            if (empty($row['end_time'])) { $open[$uid] = true; }
            $chunk = empty($row['end_time']) ? wfSeconds(wfDuration($row['start_time'], null)) : wfSeconds((string)$row['duration']);
            $seconds[$uid] = ($seconds[$uid] ?? 0) + $chunk;
        }
        $tasks = $this->countByUser(wfTbl('task'), 'completed=0');
        $leave = $this->countByUser(wfTbl('leave'), "status='pending'");
        $supply = $this->countByUser(wfTbl('supply'), "status='pending'");
        $out = [];
        foreach (wfUsers() as $user) {
            $id = (int)$user['id'];
        $out[] = wfShow([
            'id'       => $id,
            'who'      => wfContactName($user),
            'email'    => $user['email'] ?? '',
            'state'    => !empty($open[$id]) ? 'In' : 'Out',
            'today'    => wfFormatHours($seconds[$id] ?? 0),
            'tasks'    => (int)($tasks[$id] ?? 0),
            'leave'    => (int)($leave[$id] ?? 0),
            'supplies' => (int)($supply[$id] ?? 0),
        ]);
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>count($out), 'rows'=>$out])]);
    }

    public function detail(&$layout=[])
    {
        if (!wfGuard('wf_people', 1)) { return; }
        $id = (int)clean('rID', 'integer', 'get');
        $name = wfH(wfUserName($id));
        $start = date('Y-m-d 00:00:00', strtotime('-13 days'));
        $html = '<h3>'.$name.'</h3>';
        $html .= $this->listBlock('Time', dbGetMulti(wfTbl('time'), "user_id=$id AND start_time>='$start'", 'start_time DESC', '*', 40), function ($row) {
            $end = $row['end_time'] ?: 'open';
            $dur = $row['end_time'] ? $row['duration'] : wfDuration($row['start_time'], null);
            return wfH($row['start_time'].' → '.$end.' ('.$dur.')');
        });
        $html .= $this->listBlock('Tasks', dbGetMulti(wfTbl('task'), "user_id=$id", 'created_at DESC', '*', 40), function ($row) {
            $flag = !empty($row['completed']) ? 'done' : 'open';
            return wfH('['.$flag.'] '.$row['task_text']);
        });
        $html .= $this->listBlock('Work logs', dbGetMulti(wfTbl('log'), "user_id=$id AND created_at>='$start'", 'created_at DESC', '*', 40), function ($row) {
            $part = $row['part_number'] !== '' ? ' · '.$row['part_number'] : '';
            $qty = $row['quantity'] !== '' ? ' × '.$row['quantity'] : '';
            return wfH($row['log_text'].$part.$qty.' ('.$row['duration'].')');
        });
        $html .= $this->listBlock('Leave', dbGetMulti(wfTbl('leave'), "user_id=$id", 'requested_at DESC', '*', 20), function ($row) {
            return wfH($row['leave_type'].' '.$row['start_date'].' to '.$row['end_date'].' · '.$row['status'].' · '.$row['reason']);
        });
        $html .= $this->listBlock('Supplies', dbGetMulti(wfTbl('supply'), "user_id=$id", 'requested_at DESC', '*', 20), function ($row) {
            return wfH($row['quantity'].' × '.$row['part'].' · '.$row['status'].' · '.$row['reason']);
        });
        $layout = array_replace_recursive($layout, ['type'=>'divHTML', 'divs'=>['body'=>['order'=>10, 'type'=>'html', 'html'=>'<div style="padding:12px">'.$html.'</div>']]]);
    }

    public function welcome(&$layout=[])
    {
        if (!wfGuard('wf_people', 2)) { return; }
        $id = (int)clean('rID', 'integer', 'get');
        $row = wfContactUser($id);
        if (!$row) {
            $layout = array_replace_recursive($layout, wfFail('That person is not an active Bizuno user.'));
            return;
        }
        if (empty($row['email'])) {
            $layout = array_replace_recursive($layout, wfFail('That user has no email address.'));
            return;
        }
        $email = strtolower(trim((string)$row['email']));
        if (!wfEmailAllowed($email, (string)wfSet('access', 'domain', 'teamdreamweaver.com'), wfWhitelistEmails())) {
            $layout = array_replace_recursive($layout, wfFail('That email is outside the company domain and is not on the whitelist.'));
            return;
        }
        $name = wfContactName($row);
        $have = dbGetValue(wfTbl('task'), 'id', "user_id=$id AND task_text LIKE 'Welcome to the team%'");
        if (!$have) {
            dbWrite(wfTbl('task'), [
                'user_id'    => $id,
                'task_text'  => 'Welcome to the team! Please explore Workforce and clock in for your first shift.',
                'completed'  => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $html = '<h1>Welcome, '.wfH($name).'!</h1><p>Your account is ready.</p>'
            .'<p>Sign in to Bizuno and open <strong>Workforce → My Shift</strong> to clock in, log what you build, and request supplies or leave.</p>';
        if (!wfSendMail($email, $name, 'Welcome to the Dreamweaver team', $html)) { return; }
        msgAdd('Welcome email sent to '.$email.'.', 'success');
    }

    public function allow(&$layout=[])
    {
        if (!wfGuard('wf_people', 3)) { return; }
        $email = strtolower(trim((string)clean('email', 'email', 'post')));
        if ($email === '' || !str_contains($email, '@')) {
            $layout = array_replace_recursive($layout, wfFail('Enter an email address.'));
            return;
        }
        $exists = dbGetValue(wfTbl('allow'), 'id', "email='".addslashes($email)."'");
        if (!$exists) {
            dbWrite(wfTbl('allow'), [
                'email'    => $email,
                'added_by' => wfUserName(),
                'added_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $layout = array_replace_recursive($layout, wfGo('workforce/people/manager', 'Whitelist updated.'));
    }

    public function unallow(&$layout=[])
    {
        if (!wfGuard('wf_people', 4)) { return; }
        $id = (int)clean('rID', 'integer', 'get');
        if ($id > 0) { dbGetResult('DELETE FROM '.wfTbl('allow').' WHERE id='.$id); }
        $layout = array_replace_recursive($layout, wfGo('workforce/people/manager', 'Removed from the whitelist.'));
    }

    private function countByUser(string $table, string $where): array
    {
        $rows = dbGetMulti($table, $where, '', ['user_id']);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $uid = (int)$row['user_id'];
            $out[$uid] = ($out[$uid] ?? 0) + 1;
        }
        return $out;
    }

    private function listBlock(string $title, $rows, callable $line): string
    {
        $html = '<h4>'.wfH($title).'</h4><ul>';
        if (!is_array($rows) || !$rows) { return $html.'<li>None</li></ul>'; }
        foreach ($rows as $row) { $html .= '<li>'.$line($row).'</li>'; }
        return $html.'</ul>';
    }
}
