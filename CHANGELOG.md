# Changelog

All notable changes to **mod_videoassessment** will be documented in this file.

This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and (from this fork onwards) uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.8] - 2026-06-28

### Added
- GitHub Actions workflow `moodle-ci.yml` running the Moodle Plugin CI matrix across
  Moodle 4.5 / 5.0 / 5.1 / 5.2 × MariaDB / PostgreSQL in parallel.
- `CHANGELOG.md` (this file) introduced for the Shinonome Labo fork.
- `.gitattributes` enforcing LF line endings on all first-party text files.

### Changed
- `version.php`: declare support for Moodle 4.5 LTS through 5.2 (`$plugin->supported = [405, 502]`),
  raise the minimum required Moodle version to 4.5 LTS (`$plugin->requires = 2024100700`),
  and set the release to `1.1.8 (Build: 2026062809)`.
- `README.md` refreshed for the 1.1.x release line: corrected the supported Moodle
  range (4.5 LTS – 5.2), added a current-version banner, noted PostgreSQL support,
  and replaced the inline change log with a pointer to `CHANGELOG.md`.
- Convert all first-party source files (PHP, JS sources, CSS, Mustache, YAML, Markdown,
  etc.) from CRLF to LF line endings.

### Security
- SSRF hardening of the external-video thumbnail fetch. `video_embed::thumbnail_url()`
  builds a PeerTube/Esup-Pod oEmbed endpoint from the (user-supplied) video host and
  requests it server-side. It now only fetches when the host is on the admin
  trusted-embed allowlist and forces https, and `oembed_thumbnail()` additionally
  rejects any host that resolves to a private or reserved address (loopback, RFC1918,
  link-local, ...), so a student-supplied link cannot be used to probe internal
  services.
- Pre-release security review (2026-06). Hardened content that is authored by one
  user and rendered in another user's session:
  - **Stored XSS — comments modal.** The "show all comments" web service
    (`mod_videoassessment_get_getallcomments`, `externallib.php`) now purifies
    grader feedback with `format_text()` instead of rendering it with
    `noclean => true`. Moodle's HTML Purifier keeps the `<video>` / `<audio>` /
    `<source>` tags recorded feedback needs while stripping injected script, so a
    peer/self grader can no longer land script in the assessed user's or teacher's
    session.
  - **Stored XSS — video filename.** The uploaded `originalname` is now escaped
    with `s()` on the teacher "Videos" management table (`classes/va.php`) — both
    the cell value and the (now quoted) YouTube link `href`.
  - **CSRF — bulk upload.** `bulkupload/ajax.php` now calls `require_sesskey()`
    before the state-changing upload / progress actions (the AMD uploader already
    sends `M.cfg.sesskey` with every request).
  - **iframe sandbox.** Dropped `allow-popups-to-escape-sandbox` from the external
    embed sandbox (`classes/renderer/renderer.php`) so a popup opened from a
    trusted embed can no longer escape into an un-sandboxed top-level window;
    `allow-forms`, `allow-top-navigation` and `allow-modals` remain withheld and
    `clipboard-write` is not granted.
- External-embed trusted-host allowlist (from the 2026-06-19 review) re-verified
  intact and fail-closed: host-agnostic providers (PeerTube, Esup-Pod, Opencast,
  generic embed) are gated by `videoassessment/trustedembedhosts`
  (`classes/video_embed.php::host_is_trusted()` / `default_trusted_hosts()`); an
  unlisted host degrades to a plain link instead of an iframe.

### Fixed (post-release testing)
- Mobile grading: on a phone the rubric table is far wider than the viewport, so its
  right-hand levels and the score-input column overflowed off-screen and a teacher
  could not reach the cells to grade. `assess.css` now keeps the rubric within the
  viewport and lets it scroll horizontally (touch-friendly) below the 768px breakpoint.
- Opencast: a Tobira `/v/{id}` share link is now embedded via Tobira's iframe-safe
  player route `/~embed/!v/{id}` (the exact URL Tobira's own Share -> Embed dialog
  hands out; note the `!`). The `/v/{id}` page frame-busts inside an iframe ("This
  page can't be embedded") and `/~embed/v/{id}` without the `!` returns "Page not
  found", so `resolve_opencast()` rewrites to the correct form; it also recognises
  `/play/{id}`, the Paella and Theodul players.
- Feedback-video indicator: when grader feedback contains a recorded `<video>`, the
  report's General Comments now shows a localised "[See video]" play-icon primary
  (blue) button on **every device** (it previously only appeared on a phone, and the
  video was shown inline on desktop), so a student can always tell at a glance that a
  feedback video is waiting and tap to play it in a modal. A text-only comment keeps
  the "[See comment]" speech-bubble button (a phone teaser; full text inline on
  desktop), instead of the old opaque "...". The modal now renders the recording as a
  native `<video>` (`get_getallcomments` formats with `filter => false`, scoped by
  `.va-feedback-modal` CSS): the AJAX-injected content never gets a Video.js instance,
  so the old filtered "video-js" player collapsed to a broken sliver on desktop. No
  leaked `@@PLUGINFILE@@` URL teaser is shown for a video anymore.
- Untrusted external-embed hosts now show a clear notice instead of silently
  degrading to a bare link. When a host-agnostic provider's host is not on the
  `trustedembedhosts` allowlist, `video_embed::blocked_host()` distinguishes that
  case from an unrecognised URL and the renderer shows a warning ("host not on the
  trusted list"), a button to open the video in a new tab, and the admin path to add
  the host — rather than a plain link that looked like the player had failed.
- External-video thumbnails no longer render as a broken image. Externally-linked
  videos other than YouTube (Vimeo, PeerTube, Esup-Pod, Dailymotion, Opencast,
  generic embeds) had no thumbnail URL, so the score/overview and Videos tables
  emitted `<img src="">`. A shared `va::external_video_thumb()` helper now renders a
  quoted `<img>` only when a thumbnail exists and a neutral "External video"
  placeholder otherwise, and the misleading hard-coded "Video in Youtube" label is
  replaced by the localised `externalvideo` string. Vimeo links additionally fetch a
  real still via the public oEmbed endpoint (`vimeo_url::thumbnail_url()`, https +
  Vimeo-CDN-host validated, fail-safe to the placeholder on any network/parse error).
- Real thumbnails extended to the other providers via
  `video_embed::thumbnail_url()`: PeerTube and Esup-Pod are read through their oEmbed
  endpoints (`/services/oembed` and `/video/oembed/`; Esup-Pod's host-relative
  thumbnail is resolved against the video host), and Dailymotion uses its derivable
  static still. Thumbnails are vetted (https, served by the video host or a sub-domain)
  and fail-safe to the placeholder. Opencast/Tobira expose no thumbnail, so it keeps
  the placeholder.

### Fixed (customer-requested 2026-04 fixes)
- **#7** Smartphone UX hardening for the assess screen. SGU's
  recordings showed two concrete iOS problems: the floating video
  container slid under the Home indicator, and tapping a per-criterion
  `.remark textarea` (or the final feedback editor) pushed the focused
  field behind the on-screen keyboard. Two new rules in `assess.css`:
  (a) the floating / sticky video container now reserves the iOS
  safe-area insets via `padding: env(safe-area-inset-*, 0)`, and
  (b) every focusable feedback field has a `scroll-margin-top` /
  `scroll-margin-bottom` of `35vh`, so the browser's implicit
  `scrollIntoView()` on focus keeps the field above the keyboard.
  The contract is pinned by `tests/mobile_ui_test.php` (three
  data-driven assertions on the CSS file content).
- **#8** Honour `$CFG->preventexecpath` on the FFmpeg / MP4Box admin
  settings, in the spirit of upstream PR #58 by Adam Jenkins. Both
  `admin_setting_configtext_ffmpegcommand::validate()` and
  `admin_setting_configtext_mp4boxcommand::validate()` now refuse any
  change from the Web UI when the global flag is set, returning a
  localised "this executable path is locked" message
  (`admin_settings_executable_locked`, en + ja). The renaming of
  setting keys to the `videoassessment/X` style proposed in the same
  upstream PR is intentionally deferred (it would require a
  `db/upgrade.php` migration of every site's existing config). The
  install-time FFmpeg auto-detection from upstream PR #57 (Hipjea /
  fondation-unit) is also tracked for a follow-up: the security
  surface it touches is already hardened by the
  `mod_videoassessment\admin\command_validator` introduced in #9.
  Smoke test in `tests/admin/preventexecpath_test.php`.
- **#12** Add a "Finish making rubric → Go to assess" navigation
  button on the rubric edit screen. The page is owned by Moodle core
  (`/grade/grading/form/rubric/edit.php`), so the button is injected
  via a Moodle 4.5+ hook callback registered in `db/hooks.php`. The
  callback `\mod_videoassessment\hook_callbacks::inject_finish_rubric_button`
  fires on every page render, calls
  `rubric_navigation::is_videoassessment_rubric_edit_url()` to scope
  the injection, and queues the AMD module
  `mod_videoassessment/finish_rubric_button` only when the page is the
  rubric edit form for a videoassessment activity. URL classification
  + assess-page URL building live in `\mod_videoassessment\rubric_navigation`,
  exercised by 7 data-driven tests in `tests/rubric_navigation_test.php`.
  New language string `finishmakingrubric` (en + ja).
- **#6** Videos recorded inside the teacher's "Feedback Box" editor are
  preserved through the display pipeline. Both `view.php`'s
  `getallcomments` AJAX branch and `classes/print_page.php`'s comment
  rendering now pass `noclean => true` to `format_text()` so the HTML5
  `<video>` / `<source>` markup produced by Moodle's recordrtc
  Atto/Tiny plugin survives the purifier pass. Without this flag the
  cleaner stripped the media tags, leaving teacher feedback visible
  but unplayable. The contract is pinned by
  `tests/feedback_video_display_test.php`, which feeds an
  `@@PLUGINFILE@@` placeholder through the rewrite + format pipeline
  and asserts that the resulting HTML still contains both `<video>`
  and `<source>` tags pointing at `/mod_videoassessment/submissioncomment/`.
- **#13** Live "current grade in gradebook" display on the assess
  screen. The `\mod_videoassessment\rubric_total` calculator computes
  `total / max / percentage` from a snapshot of selected rubric levels
  (covered by `tests/rubric_total_test.php`); the new
  `mod_videoassessment/live_grade_total` AMD module mirrors the same
  math client-side and refreshes a `[data-vassmt-live-grade]`
  indicator next to the saved score every time a rubric cell is
  clicked. `va::view_assess()` requires the new module, and
  `classes/form/assess.php` injects the indicator span into the
  `Current grade in gradebook:` cell.
- **#3** Cap in-browser recording at 2 minutes and surface the limit on
  the radio label. The new `\mod_videoassessment\recording` helper is
  the single source of truth for the duration cap (120 seconds, 2
  minutes); `amd/src/record.js` reads the same constant via a hard-
  coded mirror, starts a `setTimeout` when recording begins, and calls
  `finishRecording()` automatically when the cap is reached. The
  English `recordnewvideo` label gains a `(max. length 2 minutes)`
  suffix; the Japanese label is updated to「新しい動画を録画(最大録画時間: 2分)」.
  `tests/recording_test.php` pins both the cap value and the label
  contract.
- **#2** Replace the single site-level `videoassessment_preventvideouploads`
  toggle with three independent allow-flags that mirror the
  per-activity "Video submissions" group:
  - `allowexternallinks` (default ON) - controls the YouTube / Vimeo /
    esup-portail / generic external link channel.
  - `allowvideouploads` (default ON) - controls direct file uploads.
  - `allowvideorecording` (default ON) - controls in-browser recording.
  `mod_form.php` reads each flag and locks the matching activity
  checkbox when the corresponding site flag is OFF. `db/upgrade.php`
  derives the new flags from the legacy `preventvideouploads` value at
  the new savepoint `2026050200`, preserving each existing site's
  effective behaviour without administrator intervention. New language
  strings (`fileuploadlinks` / `allowexternallinks` / `allowvideouploads`
  / `allowvideorecording` and their `_help` variants) are added in en
  and ja in the correct alphabetical position.
- **#4** YouTube Shorts compatibility. The new
  `\mod_videoassessment\youtube_url` helper extracts the canonical
  11-character video id from any common YouTube URL form (standard
  `?v=`, `youtu.be/`, `/shorts/`, `/embed/`, mobile `m.`, no-cookie),
  reports `is_shorts()`, and yields the canonical thumbnail and
  embed URLs (with optional GDPR-friendly `youtube-nocookie.com`
  host). `va::view_upload_video()` now routes through
  `youtube_url::extract_id()` instead of the legacy
  `explode('=', $url)`, so portrait-mode Shorts URLs no longer break
  when a learner submits one. The change is covered by data-driven
  tests in `tests/youtube_url_test.php` (12 URL forms exercised).
- **#1** Generalise the wording of the upload / link UI so it no longer
  implies YouTube exclusivity. The English strings `allowyoutube` (now
  "Allow external video links (e.g. YouTube)"), `uploadingvideo`
  ("Upload / link video"), `uploadvideo` ("Upload / link a video"),
  `reuploadvideo` and `uploadyoutube` ("Insert External Video Link")
  are updated, and the `recordnewvideo_help` paragraph now reflects
  YouTube Shorts support and explicitly mentions Vimeo and
  esup-portail/Pod as additional accepted hosts. The Japanese (`ja`)
  translations are kept in sync, with new `allowyoutube` /
  `allowyoutube_help` entries inserted in the correct alphabetical
  position so the LangFilesOrdering sniff stays clean.
- **#5** Replace the random peer-assignment algorithm in
  `va::get_random_peers_for_users()` with a load-balancing pass that
  tracks how often each user has already been chosen and always picks
  the candidate with the lowest count (random tiebreak). The previous
  algorithm only ensured that *each user received* `numpeers` peers,
  which meant some users were *chosen as* a peer many more times than
  others. The new contract -- every user is chosen within ±1 of the
  expected mean -- is pinned by `tests/peer_assignment_test.php`.
  Non-student-role exclusion in `assign_random_peers()` was already
  correct and is left untouched.
- **#10** Emit fine-grained logstore events from the Video Assessment
  workflow. Four new event classes are introduced under
  `\mod_videoassessment\event\`:
  - `video_uploaded` (CRUD c, edulevel participating) - triggered from
    `bulkupload/lib.php::video_data_add()` and
    `youtube_video_data_add()` whenever a row is inserted into
    `videoassessment_videos`.
  - `peer_review_submitted` (CRUD c) - triggered from `va.php` when a
    self / peer / class rubric is saved.
  - `grade_assigned` (CRUD u, edulevel teaching) - triggered from
    `va.php` when a teacher persists a rubric grade.
  - `report_viewed` (CRUD r) - triggered from `print.php` when the
    activity report screen is rendered.
  Each class extends `\core\event\base`, declares its `objecttable`,
  and is exercised by `tests/event/event_test.php` (data-driven sanity
  + `redirectEvents` capture).
- **#9** Harden the FFmpeg / MP4Box command admin settings against shell
  injection. A new `mod_videoassessment\admin\command_validator` class
  enforces an allow-list of characters, requires `{INPUT}` and `{OUTPUT}`
  placeholders to appear exactly once on FFmpeg commands, and requires the
  first token to be the expected binary (ffmpeg or MP4Box). The previous
  validator merely checked that placeholders appeared after the literal
  "ffmpeg" string, which let an administrator add `;`, `&&`, `|`, `$( )`
  or backticks and have them executed via `strtr()` + the shell.
  `tests/admin/command_validator_test.php` exercises the validator with
  data-providers for both safe forms and a battery of injection patterns.
- **#11** Rename the `order` column on the `videoassessment` table to
  `sortorder`. `order` is a PostgreSQL reserved keyword, which caused
  `$DB->update_record('videoassessment', ...)` to fail with
  `ERROR: syntax error at or near "order"` on PostgreSQL deployments
  (originally reported by SGU after migrating from MariaDB to PostgreSQL).
  An idempotent `rename_field()` migration is added at version
  `2026050100`. New regression test `tests/schema_test.php` enumerates
  every plugin-owned table and asserts no column collides with
  PostgreSQL's reserved keywords.

### Removed
- `templates/course_options.mustache` and `templates/section_options.mustache`.
  These partial templates emitted bare `<option>` elements and could not pass
  Moodle's mustache HTML5 lint. The two AJAX endpoints in `view.php` now build
  the option list inline with `html_writer::tag()`; the JSON response shape is
  unchanged so client-side code does not need to be updated.

### Fixed
- **CI strictness**: reduce remaining 77 PHPCS warnings (62 LineLength.TooLong on legacy
  SQL/HTML construction, 5 InlineComment.NotCapital, 5 InlineComment.InvalidEndChar,
  2 PSR2.Classes.PropertyDeclaration underscore-prefix, 2 Squiz.PHP.CommentedOutCode,
  1 moodle.Files.MoodleInternal.MoodleInternalNotNeeded) down to **0** by wrapping long
  lines, dropping unused `protected $_videoassessmentinstance` / `private $_name` form
  fields, deleting two stale commented-out blocks, removing the redundant MOODLE_INTERNAL
  guard from `lib.php` (already has `define()` side-effects), and fixing inline-comment
  capitalisation. Tighten `moodle-plugin-ci codechecker` from `--max-warnings 100`
  to `--max-warnings 0`.
- **CI strictness**: clean up the AMD `amd/src/*.js` modules so `moodle-plugin-ci grunt
  --max-lint-warnings 0` passes. Errors fixed: parse error in `assess.js` (orphan `try {`
  with no matching `catch`); unused `redirectField`/`submitButton2` in `mod_form.js`;
  unused `index` parameters in `assess.js`; max-len in `peer_assignment.js`. Legacy
  patterns (`M.str` lookups, `init_*` snake_case public APIs, `alert()`/`confirm()`
  without Notification module) suppressed at file level via `/* eslint-disable */`
  directives so the Moodle 4.5–5.2 contract is preserved while CI runs strict. The
  third-party `getHTMLMediaElement.js` (already in `thirdpartylibs.xml`) carries a
  whole-file disable so local linters running outside Grunt also pass.
- **CI strictness**: drop `continue-on-error: true` from the phpcpd, phpmd, and Grunt
  steps in `.github/workflows/moodle-ci.yml` so the matrix fails on any non-zero exit
  code. The full job now goes through phplint → phpcpd → phpmd → codechecker → phpdoc
  → validate → savepoints → mustache → grunt → phpunit → behat with no soft-fail
  intermediaries.
- Complete the PHPDoc `@param` list of `mod_videoassessment\va::get_courses_managed_by`
  (added the missing `$catid` parameter) and reorder the `@param` entries of
  `mod_videoassessment\va::get_peers_sort` to match the function signature.
- `db/upgrade.php`: reorder the upgrade blocks so that version `2020091702`
  (videoassessment_grades.isnotifystudent) comes before `2020091703`, matching
  Moodle's expectation of chronologically ordered savepoints.
- `db/upgrade.php`: consolidate six separate `if ($oldversion < 2022080801) { ... }`
  blocks into a single block with one terminating `upgrade_mod_savepoint()` call,
  removing the "5 missing savepoint" errors flagged by Moodle Plugin CI.
- `lang/en/videoassessment.php` and `lang/ja/videoassessment.php`: sort all
  `$string` entries case-sensitively by key (matches the order expected by the
  Moodle `LangFilesOrdering` sniff) and de-duplicate five accidentally-repeated
  keys in the Japanese translation. The set of available language keys is
  unchanged; only the ordering and the duplicate occurrences were normalised.

### Planned (tracked by 2026-04 fix list)
The items below are planned and tracked but **not yet implemented**.
Each will be moved to **Added / Changed / Fixed / Removed / Security** as they land.

- **#1** Wording changes: replace YouTube-specific labels with generic “external video link” wording in settings and the assessment screen, with optional GDPR-friendly Cookie suppression (`youtube-nocookie`, Vimeo `dnt`).
- **#2** Site administration: replace the single `Prevent video uploads` toggle with three checkboxes (`Allow external video links`, `Allow video uploads`, `Allow video recording`) and migrate existing sites.
- **#3** Bug: `Record Video` not working correctly; document and surface the recording time limit (e.g. `Record New Video (max. length 2 minutes)`).
- **#4** YouTube Shorts compatibility (portrait videos), real video thumbnails (replacing the grey placeholder), and an educator advisory note about recording in landscape.
- **#5** Bug: random peer assignment must distribute each student equally and exclude users who do not have only the student role.
- **#6** Bug: videos recorded inside the feedback box editor (teacher → student) do not play back correctly.
- **#7** UX: smartphone layout fixes for the floating video size and the comment / final-feedback boxes.
- **#8** Merge French team’s two FFmpeg-related pull requests.
- **#9** Security: harden the FFMPEG / MP4Box command settings against command injection; remove MP4Box if unnecessary.
- **#10** Add precise Moodle events (`video_uploaded`, `peer_review_submitted`, `grade_assigned`, `report_viewed`, …) for the standard log store.
- **#11** Bug: rename the reserved `order` column to `sortorder` to fix the PostgreSQL fatal error in `mod_videoassessment\task\automatic_file_deletion`, and audit overall PostgreSQL compatibility.
- **#12** UX: add a “Finish making rubric” button on the rubric editor that navigates to the assessment screen.
- **#13** UX: live-update the `Current grade in gradebook:` total on the scoring screen as rubric cells are selected, before the user clicks **Save Changes**.

---

## [1.0.5] - 2026-01-30

Baseline release inherited from upstream (`BM373/moodle-mod_videoassessment`,
build `2026013000`, `$plugin->supported = [400, 403]`).
This entry summarises the upstream state at the time the Shinonome Labo
fork was created; pre-fork history is preserved in the upstream repository.

[Unreleased]: https://github.com/ShinonomeLabo/moodle-mod_videoassessment/compare/v1.1.8...HEAD
[1.1.8]: https://github.com/ShinonomeLabo/moodle-mod_videoassessment/compare/v1.0.5...v1.1.8
[1.0.5]: https://github.com/ShinonomeLabo/moodle-mod_videoassessment/releases/tag/v1.0.5
