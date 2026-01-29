<?php
/**
 * Google Sheets Integration
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Integration_Google_Sheets class
 * 
 * Handles Google Sheets integration for form entries.
 */
class BAF_Integration_Google_Sheets extends BAF_Integration_Abstract {

    /**
     * Integration ID
     *
     * @var string
     */
    protected $id = 'google_sheets';

    /**
     * OAuth token URL
     *
     * @var string
     */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Sheets API URL
     *
     * @var string
     */
    const API_URL = 'https://sheets.googleapis.com/v4/spreadsheets';

    /**
     * Process entry - append to sheet
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param array     $config Integration config.
     */
    public function process( $entry, $form, $config ) {
        $spreadsheet_id = $config['spreadsheet_id'] ?? '';
        $sheet_name = $config['sheet_name'] ?? 'Sheet1';

        if ( empty( $spreadsheet_id ) ) {
            throw new Exception( __( 'Spreadsheet ID not configured.', 'bits-accessible-forms' ) );
        }

        // Get access token
        $access_token = $this->get_access_token();

        // Prepare row data
        $row_data = $this->prepare_row_data( $entry, $form, $config );

        // Append to sheet
        $this->append_row( $spreadsheet_id, $sheet_name, $row_data, $access_token );

        /**
         * Fires after data is sent to Google Sheets
         *
         * @param BAF_Entry $entry Entry object.
         * @param BAF_Form  $form  Form object.
         * @param array     $row_data Row data sent.
         */
        do_action( 'baf_google_sheets_row_added', $entry, $form, $row_data );
    }

    /**
     * Get OAuth access token
     *
     * @return string Access token.
     * @throws Exception On error.
     */
    private function get_access_token() {
        // Check for cached token
        $cached = get_transient( 'baf_google_sheets_token' );
        if ( $cached ) {
            return $cached;
        }

        $refresh_token = $this->get_setting( 'refresh_token' );
        $client_id = $this->get_setting( 'client_id' );
        $client_secret = $this->get_setting( 'client_secret' );

        if ( empty( $refresh_token ) || empty( $client_id ) || empty( $client_secret ) ) {
            throw new Exception( __( 'Google Sheets credentials not configured.', 'bits-accessible-forms' ) );
        }

        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error_description'] ?? $body['error'] );
        }

        $token = $body['access_token'];
        $expires_in = $body['expires_in'] ?? 3600;

        // Cache token
        set_transient( 'baf_google_sheets_token', $token, $expires_in - 60 );

        return $token;
    }

    /**
     * Prepare row data from entry
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param array     $config Integration config.
     * @return array Row data.
     */
    private function prepare_row_data( $entry, $form, $config ) {
        $mapping = $config['column_mapping'] ?? array();
        $field_values = $entry->get_field_values();
        $row = array();

        if ( ! empty( $mapping ) ) {
            // Use explicit mapping
            foreach ( $mapping as $column => $field_id ) {
                $value = $this->get_mapped_value( $entry, $field_id, $field_values );
                $row[] = $value;
            }
        } else {
            // Auto-map: add timestamp, then all field values
            $row[] = $entry->created_at;
            $row[] = $entry->id;

            foreach ( $form->get_fields() as $field ) {
                if ( in_array( $field['type'], array( 'section', 'html', 'submit', 'page' ), true ) ) {
                    continue;
                }

                $field_id = $field['id'];
                $value = $field_values[ $field_id ] ?? '';

                if ( is_array( $value ) ) {
                    $value = implode( ', ', $value );
                }

                $row[] = (string) $value;
            }
        }

        return $row;
    }

    /**
     * Get mapped value
     *
     * @param BAF_Entry $entry        Entry object.
     * @param string    $field_id     Field ID or special value.
     * @param array     $field_values Field values.
     * @return string Value.
     */
    private function get_mapped_value( $entry, $field_id, $field_values ) {
        // Special fields
        switch ( $field_id ) {
            case '{entry_id}':
                return $entry->id;
            case '{created_at}':
                return $entry->created_at;
            case '{user_id}':
                return $entry->user_id;
            case '{ip_address}':
                return $entry->ip_address;
            case '{source_url}':
                return $entry->source_url;
            case '{status}':
                return $entry->status;
        }

        // Regular field
        $value = $field_values[ $field_id ] ?? '';

        if ( is_array( $value ) ) {
            $value = implode( ', ', $value );
        }

        return (string) $value;
    }

    /**
     * Append row to sheet
     *
     * @param string $spreadsheet_id Spreadsheet ID.
     * @param string $sheet_name     Sheet name.
     * @param array  $row_data       Row data.
     * @param string $access_token   Access token.
     * @throws Exception On API error.
     */
    private function append_row( $spreadsheet_id, $sheet_name, $row_data, $access_token ) {
        $range = urlencode( $sheet_name . '!A:Z' );
        $url = self::API_URL . '/' . $spreadsheet_id . '/values/' . $range . ':append';
        $url .= '?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'values' => array( $row_data ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error']['message'] ?? 'Unknown error' );
        }
    }

    /**
     * Create headers row in sheet
     *
     * @param BAF_Form $form   Form object.
     * @param array    $config Integration config.
     * @return bool True on success.
     */
    public function create_headers( $form, $config ) {
        $spreadsheet_id = $config['spreadsheet_id'] ?? '';
        $sheet_name = $config['sheet_name'] ?? 'Sheet1';

        if ( empty( $spreadsheet_id ) ) {
            return false;
        }

        $access_token = $this->get_access_token();
        $headers = array( 'Timestamp', 'Entry ID' );

        foreach ( $form->get_fields() as $field ) {
            if ( in_array( $field['type'], array( 'section', 'html', 'submit', 'page' ), true ) ) {
                continue;
            }
            $headers[] = $field['label'] ?? $field['id'];
        }

        $range = urlencode( $sheet_name . '!A1' );
        $url = self::API_URL . '/' . $spreadsheet_id . '/values/' . $range;
        $url .= '?valueInputOption=RAW';

        $response = wp_remote_request( $url, array(
            'method'  => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'values' => array( $headers ),
            ) ),
        ) );

        return ! is_wp_error( $response );
    }

    /**
     * Get available spreadsheets
     *
     * @return array Spreadsheets list.
     */
    public function get_spreadsheets() {
        try {
            $access_token = $this->get_access_token();
        } catch ( Exception $e ) {
            return array();
        }

        $response = wp_remote_get( 'https://www.googleapis.com/drive/v3/files?q=mimeType%3D%27application%2Fvnd.google-apps.spreadsheet%27&fields=files(id%2Cname)', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['files'] ?? array();
    }

    /**
     * Get settings fields
     *
     * @return array Settings fields.
     */
    public function get_settings_fields() {
        return array(
            'client_id' => array(
                'type'        => 'text',
                'label'       => __( 'Client ID', 'bits-accessible-forms' ),
                'description' => __( 'Google Cloud Console OAuth Client ID', 'bits-accessible-forms' ),
            ),
            'client_secret' => array(
                'type'        => 'password',
                'label'       => __( 'Client Secret', 'bits-accessible-forms' ),
                'description' => __( 'Google Cloud Console OAuth Client Secret', 'bits-accessible-forms' ),
            ),
            'refresh_token' => array(
                'type'        => 'password',
                'label'       => __( 'Refresh Token', 'bits-accessible-forms' ),
                'description' => __( 'OAuth Refresh Token (obtained via authorization flow)', 'bits-accessible-forms' ),
            ),
        );
    }

    /**
     * Test connection
     *
     * @return true|WP_Error True or error.
     */
    public function test_connection() {
        try {
            $this->get_access_token();
            return true;
        } catch ( Exception $e ) {
            return new WP_Error( 'connection_failed', $e->getMessage() );
        }
    }

    /**
     * Get OAuth authorization URL
     *
     * @return string Authorization URL.
     */
    public function get_auth_url() {
        $client_id = $this->get_setting( 'client_id' );
        $redirect_uri = admin_url( 'admin.php?page=baf-settings&tab=integrations&auth=google_sheets' );

        $params = array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'scope'         => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.readonly',
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        );

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
    }

    /**
     * Exchange authorization code for tokens
     *
     * @param string $code Authorization code.
     * @return array|WP_Error Tokens or error.
     */
    public function exchange_code( $code ) {
        $client_id = $this->get_setting( 'client_id' );
        $client_secret = $this->get_setting( 'client_secret' );
        $redirect_uri = admin_url( 'admin.php?page=baf-settings&tab=integrations&auth=google_sheets' );

        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirect_uri,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
        }

        return $body;
    }
}
