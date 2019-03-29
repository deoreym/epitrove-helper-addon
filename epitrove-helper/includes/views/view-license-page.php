<?php

namespace Licensing\Views;

// Save licensing data
if (isset($_POST)) {
    $email_notices = array();
    $license_notices = array();
    $email_notices = $this->saveEmailDetails();
    $license_notices = $this->performLicenseActions();

    if (! empty($email_notices)) {
        ?>
        <div class="notice notice-<?php echo $email_notices['type']; ?>">
            <p><?php echo $email_notices['message']; ?></p>
        </div>
        <?php
    }

    if (! empty($license_notices)) {
        $license_data = json_decode(wp_remote_retrieve_body($license_notices));
        ?>
        <div class="notice notice-info">
            <span class='hidden'><?php echo __('Code :', 'epitrove-licensing') . $license_data->code; ?></span>
            <p>
                <?php echo $license_data->message; ?>
            </p>
        </div>
        <?php
    }
}

?>
<div class="wrap">
    <h2>
        <?php echo __('Epitrove License Options', $this->pluginSlug); ?>
    </h2>

    <form method="post" action="">
        <p>
            <label for="epi_registered_email">
                <?php _e('Registered Email Address : ', 'epitrove-licensing') ?>
            </label>
            <input type="text" name="epi_registered_email" value="<?php echo $this->getRegisteredEmail(); ?>">
            <input type="submit" class="button" name="epitrove-email-save" value="Save Email" />
        </p>
        <br>
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
            </tbody>
        </table>
    </form>
</div>
