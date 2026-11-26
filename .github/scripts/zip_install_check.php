<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CI check: install the release ZIP on a fresh Moodle the way an administrator does.
 *
 * Usage:
 *   php zip_install_check.php install <path to Moodle config.php> <ZIP>
 *     Same steps as Site administration > Plugins > Install plugins: read the
 *     component from the ZIP, validate it, then put the code in place.
 *     Run admin/cli/upgrade.php afterwards, as admin/index.php would.
 *   php zip_install_check.php verify <path to Moodle config.php> <expected version>
 *     Checks that the plugin is installed at that version and that an
 *     activity can be created.
 *
 * Not part of the plugin package (.gitattributes export-ignore).
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// This script lives outside Moodle, so config.php comes from the command line.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
[$mode, $configphp, $arg] = array_slice($argv, 1) + [null, null, null];
if (!in_array($mode, ['install', 'verify'], true) || !$configphp || !$arg) {
    fwrite(STDERR, "Usage: php zip_install_check.php install|verify <config.php> <ZIP|version>\n");
    exit(2);
}
// Moodle's setup changes the working directory, so resolve the ZIP's path first.
if ($mode === 'install' && realpath($arg) !== false) {
    $arg = realpath($arg);
}
require($configphp);
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/upgradelib.php');

$expectedcomponent = 'mod_videoassessment';

if ($mode === 'install') {
    require_once($CFG->dirroot . '/admin/tool/installaddon/classes/installer.php');

    if (!is_readable($arg)) {
        cli_error("ZIP not found: {$arg}");
    }
    // Where the installer puts the code: <mod plugin type directory>/videoassessment.
    $dir = core_component::get_plugin_types()['mod'] . '/videoassessment';
    if (file_exists($dir)) {
        cli_error("{$dir} already exists; this check needs a site without the plugin.");
    }

    // The upload form reads the component from version.php inside the ZIP.
    $component = tool_installaddon_installer::instance()->detect_plugin_component($arg);
    if ($component !== $expectedcomponent) {
        cli_error('The ZIP declares component ' . var_export($component, true) . ", expected {$expectedcomponent}.");
    }
    cli_writeln("Detected component: {$component}");

    // The page keeps its own copy of the upload, as tool_installaddon does.
    $storage = make_temp_directory('tool_installaddon/' . random_string(12));
    $zipcopy = $storage . '/plugin.zip';
    copy($arg, $zipcopy);
    $installable = [(object)['component' => $component, 'zipfilepath' => $zipcopy]];

    $pluginman = core_plugin_manager::instance();
    cli_writeln('Validating (the "Install plugin from ZIP file" result page):');
    if (!$pluginman->install_plugins($installable, false, false)) {
        cli_error('Validation failed.');
    }
    cli_writeln('');
    // The Continue button on that page puts the code in place.
    if (!$pluginman->install_plugins($installable, true, true)) {
        cli_error('Installing the code failed.');
    }
    if (!is_file($dir . '/version.php')) {
        cli_error("The code is not in place after installing: {$dir}/version.php is missing.");
    }
    cli_writeln("Code installed in {$dir}. Run admin/cli/upgrade.php next.");
    exit(0);
}

// Mode verify, after admin/cli/upgrade.php.
$expectedversion = $arg;
$installed = get_config($expectedcomponent, 'version');
if ((string)$installed !== (string)$expectedversion) {
    cli_error("Installed version is " . var_export($installed, true) . ", expected {$expectedversion}.");
}
if (!$DB->record_exists('modules', ['name' => 'videoassessment'])) {
    cli_error('The module is not registered in the modules table.');
}
$info = core_plugin_manager::instance()->get_plugin_info($expectedcomponent);
cli_writeln("Installed {$expectedcomponent} {$installed} ({$info->release}) from {$info->rootdir}");

// Creating an activity runs the plugin's add_instance code on the new tables.
require_once($CFG->libdir . '/testing/generator/lib.php');
$generator = new testing_data_generator();
$course = $generator->create_course();
$activity = $generator->create_module('videoassessment', ['course' => $course->id, 'name' => 'ZIP install check']);
if (!$DB->record_exists('videoassessment', ['id' => $activity->id])) {
    cli_error('Creating an activity failed.');
}
cli_writeln("Created activity {$activity->id} (cmid {$activity->cmid}) in course {$course->id}.");
exit(0);
