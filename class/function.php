<?php
namespace videoassess;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @param string $identifier
 * @param string $component
 * @param string|\stdClass $a
 * @return string
 */
function str($identifier, $component = '', $a = null) {
	debugging('str関数', DEBUG_DEVELOPER);
    if (!$component) {
        $component = 'mod_videoassessment';
    }

    return get_string($identifier, $component, $a);
}
