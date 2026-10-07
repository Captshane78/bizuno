<?php
/**
 * Pure-function checks for the Workforce module. Run: php tests/workforce_lib_test.php
 */

namespace bizuno;

require __DIR__.'/../src/controllers/workforce/lib.php';

$failed = 0;
function wf_assert($label, $cond)
{
    global $failed;
    if ($cond) {
        echo "ok  $label\n";
        return;
    }
    $failed++;
    echo "FAIL $label\n";
}

wf_assert('duration', wfDuration('2026-10-07 08:00:00', '2026-10-07 10:30:15') === '02:30:15');
wf_assert('duration rejects backwards', wfDuration('2026-10-07 10:00:00', '2026-10-07 09:00:00') === '00:00:00');
wf_assert('seconds', wfSeconds('02:30:15') === 9015);
wf_assert('seconds rejects junk', wfSeconds('2 hours') === 0);
wf_assert('format hours', wfFormatHours(9015) === '2h 30m');
wf_assert('same point is zero', wfHaversine(42.0, -83.0, 42.0, -83.0) < 0.01);
$km = wfHaversine(0, 0, 0, 1);
wf_assert('one degree longitude near equator', $km > 110000 && $km < 112000);
$inside = wfInsideFence(true, 42.0, -83.0, 150, 42.0, -83.0);
wf_assert('inside fence', $inside['ok'] === true);
$outside = wfInsideFence(true, 42.0, -83.0, 50, 42.01, -83.0);
wf_assert('outside fence', $outside['ok'] === false && $outside['meters'] > 50);
$off = wfInsideFence(false, 0, 0, 1, null, null);
wf_assert('fence off skips location', $off['ok'] === true);
$missing = wfInsideFence(true, 0, 0, 100, null, null);
wf_assert('fence on needs a fix', $missing['ok'] === false && $missing['meters'] < 0);

wf_assert('week starts sunday', wfPeriodStart('week', strtotime('2026-10-07 15:00:00')) === '2026-10-04 00:00:00');
wf_assert('month start', wfPeriodStart('month', strtotime('2026-10-07 15:00:00')) === '2026-10-01 00:00:00');
wf_assert('all period', wfPeriodStart('all', strtotime('2026-10-07')) === null);
wf_assert('date bound start', wfDateBound('2026-10-07', true) === '2026-10-07 00:00:00');
wf_assert('date bound end', wfDateBound('2026-10-07T18:00', false) === '2026-10-07 23:59:59');
wf_assert('datetime', wfDateTime('2026-10-07T08:05') === '2026-10-07 08:05:00');

$csv = wfCsv(['Employee Name', 'Task'], [['Ann "A"', 'cut, pack']]);
wf_assert('csv quotes', str_contains($csv, '"Ann ""A"""') && str_contains($csv, '"cut, pack"'));
$formula = wfCsv(['Task'], [['=1+1'], ['+cmd'], ["\t@x"]]);
wf_assert('csv neutralizes formulas', str_contains($formula, '"\'=1+1"') && str_contains($formula, '"\'+cmd"') && str_contains($formula, "\"'\t@x\""));

$html = wfSafeHtml('<h2>Hi</h2><script>alert(1)</script><p onclick="x()">Ok</p>');
wf_assert('strips script', !str_contains(strtolower($html), '<script') && str_contains($html, '<h2>Hi</h2>'));
wf_assert('strips handler', !str_contains(strtolower($html), 'onclick'));
$svg = wfSafeHtml('<svg/onload=alert(1)>');
wf_assert('drops svg handler', !str_contains(strtolower($svg), 'onload') && !str_contains(strtolower($svg), '<svg'));
$link = wfSafeHtml('<a href="javascript:alert(1)">click</a>');
wf_assert('drops javascript link', !str_contains(strtolower($link), 'javascript') && str_contains($link, 'click'));
$shown = wfShow(['id'=>4, 'task_text'=>'<svg/onload=alert(1)>', 'n'=>2]);
wf_assert('grid cells are escaped', $shown['id'] === 4 && $shown['n'] === 2 && !str_contains($shown['task_text'], '<svg'));
[$badLat] = wfFix(120.0, 10.0);
wf_assert('rejects impossible coordinates', $badLat === null);
[$okLat, $okLng] = wfFix(42.5, -83.25);
wf_assert('keeps a real coordinate', $okLat === 42.5 && $okLng === -83.25);

wf_assert('domain allowed', wfEmailAllowed('Pat@TeamDreamweaver.com', 'teamdreamweaver.com', []) === true);
wf_assert('whitelist allowed', wfEmailAllowed('a@gmail.com', 'teamdreamweaver.com', ['A@gmail.com']) === true);
wf_assert('stranger blocked', wfEmailAllowed('a@gmail.com', 'teamdreamweaver.com', ['b@gmail.com']) === false);

$holidays = wfHolidays();
$christmas = false;
foreach ($holidays as $day) {
    if ($day['date'] === '2026-12-25' && $day['name'] === 'Christmas Day') { $christmas = true; }
}
wf_assert('2026 christmas seeded', $christmas);
wf_assert('holiday count', count($holidays) === 20);

$prompt = wfPromptDaily('Ada', 'Total Work Duration: 2h 0m.', 'Time Entries:', 'Completed Tasks:', 'Work Logs:');
wf_assert('daily prompt names the employee', str_contains($prompt, 'Ada') && str_contains($prompt, '<h2>'));
$pay = wfPromptPayroll('10/1/2026', '10/7/2026', "Employee: Ada\nTotal Hours Worked: 8.00");
wf_assert('payroll prompt', str_contains($pay, 'Payroll Report') && str_contains($pay, 'Team Summary') && str_contains($pay, 'Ada'));
$tasks = wfPromptTasks('10/1/2026', '10/7/2026', 'Employee: Ada');
wf_assert('task prompt', str_contains($tasks, 'Task Performance Report') && str_contains($tasks, 'General Observations'));
$logs = wfPromptLogs('10/1/2026', '10/7/2026', 'Employee: Ada');
wf_assert('log prompt', str_contains($logs, 'Work Log Report') && str_contains($logs, 'Production Summary'));

if ($failed > 0) {
    fwrite(STDERR, "$failed failed\n");
    exit(1);
}
echo "all passed\n";
