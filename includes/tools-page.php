<?php
function smtp_test_render_tools_page() {
    $site_type = get_option('smtp_test_site_type');
    $site_name = sanitize_title( get_bloginfo( 'name' ) );
    ?>
    <div class="wrap">
        <h1>SMTP Test Tools</h1>

        <?php if ( isset($_GET['reset']) && $_GET['reset'] == 1 ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ Plugin settings and scheduled tasks have been reset.</p>
            </div>
        <?php endif; ?>
        <?php if ( isset( $_GET['email_sent'] ) ): ?>
        <div class="notice notice-<?php echo $_GET['email_sent'] === '1' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo $_GET['email_sent'] === '1' ? '✅ Test email sent successfully!' : '❌ Failed to send test email.'; ?></p>
        </div>
        <?php endif; ?>
        <?php if ( $site_type === 'child' ) : ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Send Test Email</th>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'smtp_test_manual_send_action', 'smtp_test_manual_nonce' ); ?>
                            <input type="hidden" name="action" value="smtp_test_manual_send">
                            <?php submit_button( 'Send Test Email Now', 'secondary', 'smtp_test_send_manual', false ); ?>
                        </form>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <hr>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Reset Plugin</th>
                <td>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'smtp_test_reset_action', 'smtp_test_reset_nonce' ); ?>
                        <input type="hidden" name="action" value="smtp_test_reset">
                        <?php submit_button( 'Reset Plugin', 'delete', 'smtp_test_reset', false ); ?>
                    </form>
                </td>
            </tr>
        </table>

    </div>
    <?php
    
}
