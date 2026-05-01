# Changelog

All notable changes to **mod_videoassessment** will be documented in this file.

This project adheres to [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and (from this fork onwards) uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- GitHub Actions workflow `moodle-ci.yml` running the Moodle Plugin CI matrix across
  Moodle 4.5 / 5.0 / 5.1 / 5.2 × MariaDB / PostgreSQL in parallel.
- `CHANGELOG.md` (this file) introduced for the Shinonome Labo fork.
- `.gitattributes` enforcing LF line endings on all first-party text files.

### Changed
- `version.php`: declare support for Moodle 4.5 LTS through 5.2 (`$plugin->supported = [405, 502]`),
  raise the minimum required Moodle version to 4.5 LTS (`$plugin->requires = 2024100700`),
  and bump the build to `2026050100`.
- Convert all first-party source files (PHP, JS sources, CSS, Mustache, YAML, Markdown,
  etc.) from CRLF to LF line endings.

### Removed
- `templates/course_options.mustache` and `templates/section_options.mustache`.
  These partial templates emitted bare `<option>` elements and could not pass
  Moodle's mustache HTML5 lint. The two AJAX endpoints in `view.php` now build
  the option list inline with `html_writer::tag()`; the JSON response shape is
  unchanged so client-side code does not need to be updated.

### Fixed
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

[Unreleased]: https://github.com/ShinonomeLabo/moodle-mod_videoassessment/compare/v1.0.5...HEAD
[1.0.5]: https://github.com/ShinonomeLabo/moodle-mod_videoassessment/releases/tag/v1.0.5
