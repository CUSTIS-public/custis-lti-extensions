<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Выпадающий список с администраторами
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_admins extends admin_setting_configselect
{
    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct($name, $visiblename, $description)
    {
        global $CFG;

        $defaultAdmin = null;
        if (!empty($CFG->siteadmins)) {
            $adminids = explode(',', $CFG->siteadmins);
            $defaultAdmin = $adminids[0];
        }

        parent::__construct(
            $name,
            $visiblename,
            $description,
            $defaultAdmin,
            null
        );
    }

    /**
     * Loads an array of choices for the configselect control
     *
     * @return bool always return true
     */
    public function load_choices()
    {
        if (is_array($this->choices)) {
            return true;
        }
        $this->choices = array();

        $admins = get_admins();

        foreach ($admins as $id => $admin) {
            $this->choices[$id] = $admin->firstname . ' ' . $admin->lastname;
        }
        return true;
    }
}
