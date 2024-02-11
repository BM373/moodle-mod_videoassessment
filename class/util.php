<?php
namespace videoassess;

defined('MOODLE_INTERNAL') || die();

class util {
    /**
     * Returns the full name label.
     *
     * @return string
     */
    public static function get_fullname_label() {
        $userfields = \core_user\fields::for_name()->with_userpic()->get_required_fields_sql('u');
        $allnamefields = array_flip(\core_user\fields::for_name()->get_required_fields());
        $namefields = array_intersect_key(array_map('get_string', $allnamefields), $allnamefields);
        return fullname((object) $namefields);
    }
}