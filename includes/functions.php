<?php
function smtp_test_encrypt_password( $password ) {
    $password = trim( $password );

    if ( empty( $password ) ) {
        return get_option( 'smtp_test_app_password' );
    }

    $maybe_decrypted = smtp_test_decrypt_password( $password );
    if ( $maybe_decrypted === false ) {
        $key = AUTH_KEY;
        $iv  = substr( hash( 'sha256', $key ), 0, 16 );
        $encrypted = openssl_encrypt( $password, 'aes-256-cbc', $key, 0, $iv );
        return base64_encode( $encrypted );
    } else {
        return $password;
    }
}

function smtp_test_decrypt_password( $encrypted ) {
    $key = AUTH_KEY;
    $iv  = substr( hash( 'sha256', $key ), 0, 16 );
    return openssl_decrypt( base64_decode( $encrypted ), 'aes-256-cbc', $key, 0, $iv );
}

function smtp_test_send_email() {
    // ✅ Force PHP timezone to match WordPress timezone
    $timezone_string = get_option( 'timezone_string' );
    if ( $timezone_string ) {
        date_default_timezone_set( $timezone_string );
    }

    $site_name = sanitize_title( get_bloginfo( 'name' ) );
    $date = strtolower( date( 'F-j' ) ); // wp_date could still give UTC in CRON
    $token = $site_name . '-' . $date;

    $to = get_option( 'smtp_test_email_to' );
    $subject = 'SMTP Test Email - Token: ' . $token;
    $body = "This is a scheduled test email from $site_name.\n\nToken: $token";
    $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

    $sent = wp_mail( $to, $subject, $body, $headers );

    if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;

    wp_redirect( admin_url( 'admin.php?page=smtp-test-tools&email_sent=' . ( $sent ? '1' : '0' ) ) );
    exit;
}

function smtp_test_check_email_token() {
     // Avoid running the full IMAP query in the block/page editor
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return '<p>📬 SMTP Test Results will display here.</p>';
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return '';
    }

    if ( is_admin() && ! wp_doing_ajax() ) {
        return '<p>📬 SMTP Test Results will display here.</p>';
    }

    // ✅ Force PHP timezone to match WordPress timezone
    $timezone_string = get_option( 'timezone_string' );
    if ( $timezone_string ) {
        date_default_timezone_set( $timezone_string );
    }

    $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';
    $username = sanitize_email( get_option( 'smtp_test_email_to' ) );
    $password = smtp_test_decrypt_password( get_option( 'smtp_test_app_password' ) );

    if ( empty( $password ) ) {
        return '<p style="color:red;">❌ IMAP connection failed: No valid password available.</p>';
    }

    $child_sites_raw = get_option( 'smtp_test_child_sites' );
    $child_sites = array_filter( array_map( 'trim', explode( "\n", $child_sites_raw ) ) );

    $inbox = @imap_open( $mailbox, $username, $password );
    if ( ! $inbox ) {
        return '<p style="color:red;">❌ IMAP connection failed: ' . imap_last_error() . '</p>';
    }

    $lookback_days = absint( get_option( 'smtp_test_lookback_days', 14 ) );
    $emails = imap_search( $inbox, 'SINCE "' . date( 'd-M-Y', strtotime( "-$lookback_days days" ) ) . '"' );

    $all_messages = [];

    if ( $emails ) {
        rsort( $emails );
        foreach ( $emails as $email_number ) {
            $overview = imap_fetch_overview( $inbox, $email_number, 0 );
            $subject = isset( $overview[0]->subject ) ? $overview[0]->subject : '';
            $body = imap_fetchbody( $inbox, $email_number, 1 );
            $date = isset( $overview[0]->date ) ? $overview[0]->date : '';
            $timestamp = strtotime( $date );
            $all_messages[] = [
                'content' => strtolower( $subject . ' ' . $body ),
                'timestamp' => $timestamp,
            ];
        }
    }

    imap_close( $inbox );

    $output = '<h2>📬 Test Email Results</h2><ul>';

    foreach ( $child_sites as $token_base ) {
        $latest_match = null;

        foreach ( $all_messages as $message ) {
            if ( strpos( $message['content'], $token_base . '-' ) !== false ) {
                if ( ! $latest_match || $message['timestamp'] > $latest_match['timestamp'] ) {
                    $latest_match = $message;
                }
            }
        }

        if ( $latest_match ) {
            $formatted_date = date( 'F j', $latest_match['timestamp'] );
            $output .= '<li>' . esc_html( $token_base ) . ': <span style="color:green;">✅ Last Successful Test: ' . esc_html( $formatted_date ) . '</span></li>';
        } else {
            $output .= '<li>' . esc_html( $token_base ) . ': <span style="color:red;">❌ No test found</span></li>';
        }
    }

    $output .= '</ul>';
    return $output;
}

