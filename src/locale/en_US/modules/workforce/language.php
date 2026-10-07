<?php
/*
 * Language for the Workforce module.
 *
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-10-07
 * @filesource /locale/en_US/modules/workforce/language.php
 */

$lang = [
    'title'       => 'Workforce',
    'description' => 'Time clock, tasks, work logs, supply requests, leave, and the company calendar from Dreamweaver Workforce.',
    'workforce'   => 'Workforce',
    'my_shift'    => 'My Shift',
    'team_board'  => 'Team Board',
    'time_entries'=> 'Time Entries',
    'work_logs'   => 'Work Logs',
    'supplies'    => 'Supplies',
    'leave'       => 'Leave',
    'calendar'    => 'Calendar',
    'people'      => 'People',
    'geo_enabled_lbl' => 'Require location to clock in',
    'geo_enabled_tip' => 'When yes, an employee clock-in must include coordinates inside the radius. The punch stores the coordinates the browser sent. That is a reported location, not proof the device was there.',
    'geo_latitude_lbl' => 'Workplace latitude',
    'geo_longitude_lbl'=> 'Workplace longitude',
    'geo_radius_lbl'   => 'Allowed radius (meters)',
    'ai_api_key_lbl'   => 'Gemini API key',
    'ai_api_key_tip'   => 'Used for the daily summary, payroll, task, and work log reports. Leave blank to keep the saved key.',
    'ai_model_lbl'     => 'Gemini model',
    'access_domain_lbl'=> 'Allowed email domain',
    'access_domain_tip'=> 'Addresses at this domain, plus the whitelist, are the external emails a manager may invite.',
];
