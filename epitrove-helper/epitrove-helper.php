<?php
/**
 * Plugin Name:       Epitrove Helper (Beta 1)
 * Plugin URI:        http://wisdmlabs.com
 * Description:       Licensing addon for all epitrove products.
 * Version:           0.1.0
 * Author:            WisdmLabs
 * Author URI:        http://wisdmlabs.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       epitrove-helper
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

function run_epitrove_helper()
{
    require plugin_dir_path(__FILE__).'includes/class-epitrove-license.php';
    new \Licensing\EpitroveLicense();
    // $plugin->run();
}
run_epitrove_helper();

// Check updates for epitrove-helper addon
require 'addon-updater/plugin-update-checker.php';
$addon_updater = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/WisdmLabs/epitrove-helper-addon',
    __FILE__,
    'epitrove-helper-addon'
);

//Optional: Set the branch that contains the stable release.
// $addon_updater->setBranch('stable-branch-name');
