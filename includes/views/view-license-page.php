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
    <div class="wdm-admin-license-management-wrap">
        <h2>License Management</h2>
        <p class="steps-meta">Activate your product license in two steps</p>
        <form method="post" action="">
            <div class="wdm-home-page-banner-text-content-license">
                <p class="legend">Step 1: Enter the Email ID used to register at Epitrove</p>
                <div class="wdm-plugin-activation-field">
                    <input type="text" name="epi_registered_email" value="<?php echo esc_attr(static::getRegisteredEmail()); ?>" <?php echo $emailFieldReadOnly; ?>>
                    <?php if(!$emailFieldReadOnly) {?>
                    <input type="submit" class="button button-primary" name="epitrove-email-save" value="Save Email" />
                    <?php } else { ?>
                    <p><i>Email address cannot be changed until all product licenses are deactivated</i></p>
                    <?php } ?>
                </div>
            </div>
            <div class="wdm-home-page-banner-text-content-license step-two">
                <p class="legend">Step 2: Enter License Keys</p>
                <table class="epitrove-license-table">
                    <tbody>
                        <tr>
                            <td class="product-name-head"><?php _e('Product Name', 'epitrove-licensing'); ?></td>
                            <td class="license-key-head"><?php _e('License Key', 'epitrove-licensing'); ?></td>
                            <td class="license-status-head"><?php _e('License Status', 'epitrove-licensing'); ?></td>
                            <td class="actions-head"><?php _e('Actions', 'epitrove-licensing'); ?></td>
                        </tr>
                        <?php $this->showAllProductLicenses(); ?>
                        <?php do_action('epitrove_display_licensing_options'); ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>
