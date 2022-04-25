<?php
namespace videoassess;

defined('MOODLE_INTERNAL') || die();

class util {
    /**
     *
     * @return string
     */
    public static function get_fullname_label() {
        if (function_exists('get_all_user_name_fields')) {
            return fullname((object)array_map('get_string', get_all_user_name_fields()));
        } else {
            return fullname((object)array_map('get_string',
                array('firstname' => 'firstname', 'lastname' => 'lastname')));
        }
    }
}
