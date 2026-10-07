---
title: Workforce handoff
status: draft
audience: [admin, developer]
last-updated: 2026-10-07
---

# Workforce handoff

This module is already in this repository. The next session should turn it on, grant roles, and deploy it. It should leave the feature set as it is unless Shane asks for a change.

Draft pull request: https://github.com/Captshane78/bizuno/pull/1
Branch: `cursor/workforce-module-879a`
Source app: `Captshane78/DW-Gemini-TC-APP` (Dreamweaver Workforce v2.0.0)

`Captshane78/Bizuno-DW` is the live Dreamweaver deployment tree. It does not contain this module. Copy the files from this branch when that site is ready for them. Do not re-port the React app into that tree.

This page is an operator handoff. It is `status: draft` so it stays out of the published user manual.

## Turn it on

1. Merge the pull request into the Bizuno tree that will run the shop.
2. Sign in as a Bizuno administrator.
3. Open **Settings → Bizuno → Tools → Clear Cache** (`administrate/tools/cacheClear`, security key `admin` level 3).
4. The next request rebuilds the registry. That calls `workforceAdmin::initialize()`, which creates the tables, and grants the role that performed the rebuild level 4 on every Workforce menu.
5. Confirm **Workforce** appears in the menu (order 65, icon `employee`).

Until that cache clear, Bizuno will not list the module. The menu is built from `src/controllers/workforce/admin.php` only after `portalModuleList` sees that file and the registry reloads.

## Add a person

Bizuno already owns accounts. Create the user under **Administration → Users** (`contacts/main/manager&type=u`). Then open **Workforce → People** and send the welcome email.

The welcome email sends only when the address ends in the invite domain (default `teamdreamweaver.com`) or is on the People whitelist (`workforce_allow`). That check does not block Bizuno login. A blank Gemini API key is left unchanged on save.

The welcome message tells the person to sign in and open **Workforce → My Shift**. If they have no task whose text starts with `Welcome to the team`, People also inserts:

> Welcome to the team! Please explore Workforce and clock in for your first shift.

Mail goes through `\bizuno\bizunoMailer`.

## Roles

Security levels follow Bizuno: 1 view, 2 add, 3 edit, 4 delete. Set them under **Administration → Roles** after the installing role has been granted level 4.

| Menu | Security key | Route | Who uses it |
| --- | --- | --- | --- |
| My Shift | `wf_shift` | `workforce/shift/manager` | Everyone who clocks in. Level 2 clocks and adds. Level 3 toggles own tasks. Level 4 deletes own tasks and work logs. |
| Team Board | `wf_board` | `workforce/board/manager` | Manager. Level 1 reads the board, runs Gemini reports, and downloads CSV. |
| People | `wf_people` | `workforce/people/manager` | Manager. Level 2 sends welcome email and clocks someone else in or out. Level 3 adds a whitelist address. Level 4 removes one. |
| Time Entries | `wf_time` | `workforce/time/manager` | Manager. Level 2 adds a punch. Level 3 edits. Level 4 deletes. |
| Tasks | `wf_tasks` | `workforce/tasks/manager` | Manager. Level 2 assigns. Level 3 toggles someone else's task. Level 4 deletes it. |
| Work Logs | `wf_logs` | `workforce/logs/manager` | Manager. Level 2 adds. Level 4 deletes someone else's log. |
| Supplies | `wf_supply` | `workforce/supply/manager` | Manager. Level 3 marks a request handled. |
| Leave | `wf_leave` | `workforce/leave/manager` | Manager. Level 3 approves or rejects. |
| Calendar | `wf_calendar` | `workforce/calendar/manager` | Manager. Level 1 is enough for My Shift to list events. Level 2 adds and seeds holidays. Level 4 deletes. |
| Settings | `wf_settings` | `workforce/admin/adminHome` | Manager or Bizuno `admin`. Level 1 opens the page. Level 3 saves. |

A floor employee only needs **My Shift**. Give managers the other keys. The dashboard **Who Is Clocked In** uses security key `wf_people` and category `workforce`.

## Settings

**Workforce → Settings**

| Group | Defaults | What it does |
| --- | --- | --- |
| Workplace location | enabled `0`, latitude `0`, longitude `0`, radius `150` meters | When enabled, an employee clock-in must include coordinates inside the radius. **Use my current location** fills the lat/lng fields in the browser. Save after that. |
| Gemini reports | model `gemini-flash-latest`, API key empty | Server-side request to `generativelanguage.googleapis.com`. The key is a password field, so a blank save keeps the stored key. |
| Invites | domain `teamdreamweaver.com` | Welcome-email gate, together with the People whitelist. |

Employee clock-in on My Shift enforces the fence on the server. A missing location while the fence is on is rejected. Manager clock-in from People or Time Entries does not enforce the fence.

## Screens

- **My Shift.** Clock in and out, live elapsed time, one open punch. Own tasks, work logs (today / week / month), supply requests, leave requests, and the calendar. **Summarize my day** asks Gemini for today's HTML summary.
- **Team Board.** Defaults to the last 14 days (today minus 13 through today). Stats for every active user. Gemini payroll, task, and work-log reports. CSV download for the same three.
- **People.** In/out, today's hours, open tasks, pending leave, pending supplies. Detail shows the last 14 days of punches and logs, plus tasks, leave, and supplies. Clock in/out, welcome email, whitelist.
- **Time Entries.** Add, edit, and delete punches. A blank end time means the punch is still open. A second open punch for the same person is refused.
- **Tasks, Work Logs, Supplies, Leave, Calendar.** Team-wide versions of the My Shift lists. Leave types are `vacation`, `sick`, and `time-off`. Supply and leave statuses are `pending` until a manager sets `handled`, or `approved` / `rejected` for leave.
- **Calendar → Seed holidays** inserts the 20 US dates from the source app (2024–2026, including Good Friday `2026-04-03` and Easter `2026-04-05`) and skips a date+name that already exists.

CSV columns match the source export:

- Payroll: `Employee Name, Date, Clock In, Clock Out, Duration (HH:MM:SS)`
- Tasks: `Employee Name, Task, Status, Created At`
- Logs: `Employee Name, Activity, Part Number, Quantity, Duration, Date`

Gemini prompts are the four prompts from `DW-Gemini-TC-APP` `services/geminiService.ts`. Reports are HTML fragments. They do not include `<html>` or `<body>`.

## Tables

Created by `wfEnsureTables()` on install, initialize, or the first Workforce page. Prefix is `BIZUNO_DB_PREFIX` plus `workforce_`.

| Table | Holds |
| --- | --- |
| `workforce_time` | `user_id`, `start_time`, `end_time` (null while open), `duration` as `HH:mm:ss` |
| `workforce_task` | `user_id`, `task_text`, `completed`, `created_at` |
| `workforce_log` | `user_id`, `log_text`, `part_number`, `quantity`, `duration` (free text), `created_at` |
| `workforce_supply` | part, quantity, reason, `pending`/`handled`, source, handled by/at |
| `workforce_leave` | `vacation`/`sick`/`time-off`, dates, reason, `pending`/`approved`/`rejected` |
| `workforce_event` | `event_date`, `event_name`, `holiday` or `event` |
| `workforce_allow` | whitelist email, unique |

Users are Bizuno contacts with `ctype_u = 1`. Workforce rows store that contact id in `user_id`.

## Files

```
src/controllers/workforce/admin.php          menu, settings, install
src/controllers/workforce/common.php         tables, clock, mail, Gemini HTTP, grids
src/controllers/workforce/lib.php            pure helpers and prompt text
src/controllers/workforce/shift.php          My Shift
src/controllers/workforce/board.php          Team Board, reports, CSV
src/controllers/workforce/people.php         People, welcome, whitelist
src/controllers/workforce/time.php           punches
src/controllers/workforce/tasks.php
src/controllers/workforce/logs.php
src/controllers/workforce/supply.php
src/controllers/workforce/leave.php
src/controllers/workforce/calendar.php
src/controllers/workforce/dashboards/wf_onclock/wf_onclock.php
src/locale/en_US/modules/workforce/language.php
tests/workforce_lib_test.php
```

Class names are `\bizuno\workforce{Page}` (`workforceAdmin`, `workforceShift`, …). The dashboard class name matches its folder: `\bizuno\wf_onclock`.

`lib.php` has no database access. Run the unit test with PHP CLI, no Bizuno install required:

```
php tests/workforce_lib_test.php
```

## Left in the source app

These Dreamweaver Workforce pieces stay there. Bizuno already has the equivalent, or copying them would fight Bizuno's account model.

- Firebase Auth, Firestore, and the secondary Firebase app used so a manager could reset a password without signing themselves out.
- Google sign-in.
- Resend. Bizuno mail sends the welcome message.
- Creating a password from Workforce. New accounts are Administration → Users.
- Signup limited to `@teamdreamweaver.com` at the login screen. The domain and whitelist gate the welcome email only.
- Browser-only geofence. The server checks employee clock-in again.

## Behaviors to expect

- A successful clock-in or clock-out reloads the page when `#wfClock` is on screen, so the success toast can disappear with the reload.
- The supply "handled" window id is `wfHandle`.
- Whitelist add/remove navigates with a full page load.
- EasyUI datagrid action column key is `action`. Hidden fields live inside `attr`.
- Menu labels in `admin.php` are plain English. Bizuno prints `label` as written.

## If the next task is the live Dreamweaver site

`Bizuno-DW` does not have `src/controllers/bizuno/admin.php`. It is scripts and deployment, not this library. When Shane wants Workforce on that site, copy this module (the `workforce` controller, the locale file, and the test) into the Bizuno library that site actually runs, clear cache, and grant roles. Do not rebuild the screens from `DW-Gemini-TC-APP`.
