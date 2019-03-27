<?php

namespace Licensing\Views;

// Save licensing data
if (isset($_POST)) {
    // $this->saveLicenseDetails();
    $this->performLicenseActions();
}
?>
<div class="wrap">
    <h2>
        <?php echo __('Epitrove License Options', $this->pluginSlug); ?>
    </h2>

    <form method="post" action="">
        <table class="epitrove-license-table">
            <thead>
                <th class="product-name-head"><?php _e('Product Name', 'epitrove-licensing'); ?></th>
                <th class="license-key-head"><?php _e('License Key', 'epitrove-licensing'); ?></th>
                <th class="license-status-head"><?php _e('License Status', 'epitrove-licensing'); ?></th>
                <th class="actions-head"><?php _e('Actions', 'epitrove-licensing'); ?></th>
            </thead>
            <tbody>
                <?php $this->showAllProductLicenses(); ?>
                <?php do_action('epitrove_display_licensing_options'); ?>
                <!-- <tr>
                    <td>
                        <input
                            type="button"
                            class="button button-primary"
                            name="epitrove-license-save"
                            value="Save Details" />
                    </td>
                </tr> -->
            </tbody>
        </table>
    </form>
</div>
