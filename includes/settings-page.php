<?php
function smtp_test_render_settings_page() {
    $site_type = get_option('smtp_test_site_type');
    $site_name = sanitize_title( get_bloginfo( 'name' ) );
    $days = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
    ?>
    <div class="wrap">
        <h1>SMTP Test Settings</h1>

        <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ Settings saved successfully!</p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'smtp_test_settings' ); ?>
            <?php do_settings_sections( 'smtp_test_settings' ); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Site Type</th>
                    <td>
                        <select name="smtp_test_site_type">
                            <option value="child" <?php selected( $site_type, 'child' ); ?>>Child Site</option>
                            <option value="parent" <?php selected( $site_type, 'parent' ); ?>>Parent Site</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Test Email inbox</th>
                    <td><input type="email" name="smtp_test_email_to" value="<?php echo esc_attr( get_option('smtp_test_email_to') ); ?>" /></td>
                </tr>
                <?php if ( $site_type === 'child' ) : ?>
                <tr valign="top">
                    <th scope="row">Test Day</th>
                    <td>
                        <select name="smtp_test_day">
                            <?php foreach ( $days as $day ) : ?>
                                <option value="<?php echo esc_attr( $day ); ?>" <?php selected( get_option('smtp_test_day', 'Friday'), $day ); ?>><?php echo esc_html( $day ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Test emails are only sent if today matches the selected day.</p>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ( $site_type === 'parent' ) : ?>
                    <tr valign="top">
                        <th scope="row">Gmail App Password</th>
                        <td>
                            <?php $encrypted = get_option('smtp_test_app_password'); $has_password = ! empty( $encrypted ); ?>
                            <input type="password" name="smtp_test_app_password" value="" placeholder="Only needed for parent site" />
                            <?php if ( $has_password ) : ?>
                                <p><em>🔒 A password is saved. Leave blank to keep it.</em></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Child Site Tokens</th>
                        <td>
                            <textarea name="smtp_test_child_sites" rows="5" cols="40" placeholder="One token per line"><?php echo esc_textarea( get_option('smtp_test_child_sites') ); ?></textarea>
                            <p class="description">Enter one token per line. Tokens should match the slugified site name from the child site.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
