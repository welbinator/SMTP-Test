<?php
function smtp_test_encrypt_password( $password ) {
    $password = trim( $password );

    // If field is empty, return current saved value to preserve it
    if ( empty( $password ) ) {
        // error_log('[SMTP Test] Password field was empty — keeping saved password.');
        return get_option( 'smtp_test_app_password' );
    }

    // If it looks already encrypted (base64, long, and decryptable), don't re-encrypt
    $maybe_decrypted = smtp_test_decrypt_password( $password );
    if ( $maybe_decrypted === false ) {
        // error_log('[SMTP Test] Encrypting fresh password input.');
        $key = AUTH_KEY;
        $iv  = substr( hash( 'sha256', $key ), 0, 16 );
        $encrypted = openssl_encrypt( $password, 'aes-256-cbc', $key, 0, $iv );
        $encoded = base64_encode( $encrypted );
        // error_log('[SMTP Test] Encrypted and base64 password: ' . $encoded);
        return $encoded;
    } else {
        // error_log('[SMTP Test] Submitted password appears already encrypted — skipping.');
        return $password;
    }
}

function smtp_test_decrypt_password( $encrypted ) {
    $key = AUTH_KEY;
    $iv  = substr( hash( 'sha256', $key ), 0, 16 );
    $decrypted = openssl_decrypt( base64_decode( $encrypted ), 'aes-256-cbc', $key, 0, $iv );
    return $decrypted;
}


function smtp_test_send_email() {
    $site_name = sanitize_title( get_bloginfo( 'name' ) );
    $date = strtolower( date( 'F-j' ) );
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
    $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';
    $username = sanitize_email( get_option( 'smtp_test_email_to' ) );
    $password = smtp_test_decrypt_password( get_option( 'smtp_test_app_password' ) );
    // error_log('[SMTP Test] Password used in IMAP login: ' . $password);


        if ( empty( $password ) ) {
            return '<p style="color:red;">❌ IMAP connection failed: No valid password available.</p>';
        }


    $child_sites_raw = get_option( 'smtp_test_child_sites' );
    $child_sites = array_filter( array_map( 'trim', explode( "\n", $child_sites_raw ) ) );

    $inbox = @imap_open( $mailbox, $username, $password );
    if ( ! $inbox ) {
        return '<p style="color:red;">❌ IMAP connection failed: ' . imap_last_error() . '</p>';
    }

    $emails = imap_search( $inbox, 'SINCE "' . date( 'd-M-Y', strtotime('-7 days') ) . '"' );
    $all_messages = [];

    if ( $emails ) {
        rsort( $emails );
        foreach ( $emails as $email_number ) {
            $overview = imap_fetch_overview( $inbox, $email_number, 0 );
            $subject = isset( $overview[0]->subject ) ? $overview[0]->subject : '';
            $body = imap_fetchbody( $inbox, $email_number, 1 );
            $all_messages[] = $subject . ' ' . $body;
        }
    }

    imap_close( $inbox );

    $output = '<h2>📬 Token Check Results</h2><ul>';

    foreach ( $child_sites as $token_base ) {
        $expected_token = $token_base . '-' . strtolower( date( 'F-j' ) );
        $found = false;

        foreach ( $all_messages as $content ) {
            if ( stripos( $content, $expected_token ) !== false ) {
                $found = true;
                break;
            }
        }

        $output .= '<li>' . esc_html( $token_base ) . ': ' . ( $found ? '<span style="color:green;">✅ Found</span>' : '<span style="color:red;">❌ Not Found</span>' ) . '</li>';
    }

    $output .= '</ul>';

    return $output;
}