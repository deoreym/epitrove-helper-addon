<?php

namespace Licensing\Views;

// Save licensing data
if (isset($_POST)) {
    $emailNotices = $this->saveEmailAddress();
    $responseStatus = $this->getResponseStatus();
    $emailFieldReadOnly = $this->isThereProductWithActiveLicense() ? 'readonly' : '';

    if (! empty($emailNotices)) {
        ?>
        <div class="notice notice-<?php echo $emailNotices['type']; ?>">
            <p><?php echo $emailNotices['message']; ?></p>
        </div>
        <?php
    }

    if (! empty($responseStatus)) {
        ?>
        <div class="notice notice-info">
            <span class="hidden"><?php echo __('Code :', 'epitrove-licensing') . $responseStatus->code; ?></span>
            <p>
                <?php echo $responseStatus->message; ?>
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
            <input type="text" name="epi_registered_email" value="<?php echo esc_attr(static::getRegisteredEmail()); ?>" <?php echo $emailFieldReadOnly; ?>>
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
