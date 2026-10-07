<?php
/*
 * Workforce module — time clock, tasks, work logs, supplies, leave, and calendar.
 * Brought over from Dreamweaver Workforce (DW-Gemini-TC-APP) onto Bizuno users,
 * roles, and mail.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /controllers/workforce/admin.php
 */

namespace bizuno;

class workforceAdmin
{
    public $moduleID = 'workforce';
    public $pageID   = 'admin';
    public $lang     = ['title'=>'Workforce', 'description'=>'Time clock, tasks, work logs, supply requests, leave, and the company calendar.'];
    public $defaults;
    public $settings;
    public $structure;

    function __construct()
    {
        $this->defaults = [
            'geo'   => ['enabled'=>'0', 'latitude'=>'0', 'longitude'=>'0', 'radius'=>'150'],
            'ai'    => ['api_key'=>'', 'model'=>'gemini-flash-latest'],
            'access'=> ['domain'=>'teamdreamweaver.com'],
        ];
        $saved = getModuleCache($this->moduleID, 'settings', false, false, []);
        $this->settings = array_replace_recursive($this->defaults, is_array($saved) ? $saved : []);
        $this->structure = [
            'dirMethods'=> ['dashboards'],
            'menuBar'   => ['child'=>[
                'workforce'=> ['order'=>65, 'label'=>'Workforce', 'icon'=>'employee', 'route'=>'bizuno/main/bizunoHome&menuID=workforce', 'child'=>[
                    'wf_shift'   => ['order'=>10, 'label'=>'My Shift',      'icon'=>'employee', 'route'=>'workforce/shift/manager'],
                    'wf_board'   => ['order'=>20, 'label'=>'Team Board',     'icon'=>'dashboard','route'=>'workforce/board/manager'],
                    'wf_people'  => ['order'=>30, 'label'=>'People',         'icon'=>'users',    'route'=>'workforce/people/manager'],
                    'wf_time'    => ['order'=>40, 'label'=>'Time Entries',   'icon'=>'date',     'route'=>'workforce/time/manager'],
                    'wf_tasks'   => ['order'=>50, 'label'=>'Tasks',          'icon'=>'journal',  'route'=>'workforce/tasks/manager'],
                    'wf_logs'    => ['order'=>60, 'label'=>'Work Logs',      'icon'=>'work',     'route'=>'workforce/logs/manager'],
                    'wf_supply'  => ['order'=>70, 'label'=>'Supplies',       'icon'=>'purchase', 'route'=>'workforce/supply/manager'],
                    'wf_leave'   => ['order'=>80, 'label'=>'Leave',          'icon'=>'date',     'route'=>'workforce/leave/manager'],
                    'wf_calendar'=> ['order'=>90, 'label'=>'Calendar',       'icon'=>'date',     'route'=>'workforce/calendar/manager'],
                    'wf_settings'=> ['order'=>99, 'label'=>'Settings',       'icon'=>'settings', 'route'=>'workforce/admin/adminHome'],
                ]],
            ]],
        ];
    }

    public function settingsStructure()
    {
        $yesNo = [['id'=>'0', 'text'=>lang('no')], ['id'=>'1', 'text'=>lang('yes')]];
        $data = [
            'geo' => ['order'=>20, 'label'=>'Workplace location', 'fields'=>[
                'enabled'  => ['order'=>10, 'label'=>lang('geo_enabled_lbl', $this->moduleID), 'tip'=>lang('geo_enabled_tip', $this->moduleID), 'values'=>$yesNo, 'attr'=>['type'=>'select', 'value'=>$this->settings['geo']['enabled']]],
                'latitude' => ['order'=>20, 'label'=>lang('geo_latitude_lbl', $this->moduleID), 'attr'=>['type'=>'text', 'value'=>$this->settings['geo']['latitude']]],
                'longitude'=> ['order'=>30, 'label'=>lang('geo_longitude_lbl', $this->moduleID),'attr'=>['type'=>'text', 'value'=>$this->settings['geo']['longitude']]],
                'radius'   => ['order'=>40, 'label'=>lang('geo_radius_lbl', $this->moduleID),   'attr'=>['type'=>'text', 'value'=>$this->settings['geo']['radius']]],
            ]],
            'ai' => ['order'=>30, 'label'=>'Gemini reports', 'fields'=>[
                'api_key'=> ['order'=>10, 'label'=>lang('ai_api_key_lbl', $this->moduleID), 'tip'=>lang('ai_api_key_tip', $this->moduleID), 'attr'=>['type'=>'password', 'value'=>$this->settings['ai']['api_key']]],
                'model'  => ['order'=>20, 'label'=>lang('ai_model_lbl', $this->moduleID), 'attr'=>['type'=>'text', 'value'=>$this->settings['ai']['model']]],
            ]],
            'access' => ['order'=>40, 'label'=>'Invites', 'fields'=>[
                'domain'=> ['order'=>10, 'label'=>lang('access_domain_lbl', $this->moduleID), 'tip'=>lang('access_domain_tip', $this->moduleID), 'attr'=>['type'=>'text', 'value'=>$this->settings['access']['domain']]],
            ]],
        ];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    public function install()
    {
        wfBoot();
    }

    public function initialize()
    {
        wfBoot();
        return true;
    }

    public function adminHome(&$layout=[])
    {
        if (!wfCan('wf_settings', 1) && !wfCan('admin', 1)) {
            msgAdd(lang('err_no_permission').' [wf_settings]');
            return;
        }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure()), [
            'divs' => ['here'=>['order'=>18, 'type'=>'html', 'html'=>'<p><button type="button" onclick="wfHere();">Use my current location</button> Fills the latitude and longitude. Save settings after.</p>']],
            'jsHead'=>['wfHere'=>"function wfSetVal(id, v){ var el = jqBiz('#'+id); if (el.data('textbox')) { el.textbox('setValue', v); } else { el.val(v); } } function wfHere(){ if (!navigator.geolocation) { alert('Geolocation is not supported by this browser.'); return; } navigator.geolocation.getCurrentPosition(function(p){ wfSetVal('geo_latitude', p.coords.latitude); wfSetVal('geo_longitude', p.coords.longitude); }, function(){ alert('Could not read this device location.'); }, {enableHighAccuracy:true, timeout:10000, maximumAge:0}); }"],
        ]);
    }

    public function adminSave()
    {
        if (!wfCan('wf_settings', 3) && !wfCan('admin', 3)) {
            msgAdd(lang('err_no_permission').' [wf_settings]');
            return;
        }
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }
}

bizAutoLoad(__DIR__.'/common.php', 'wfBoot', 'function');
