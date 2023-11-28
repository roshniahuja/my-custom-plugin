<?php
/*
Plugin Name: My Custom Plugin
Version: 1.0
Requires at least: 5.0
Requires PHP: 7.0
Author: Roshni Ahuja
Author URI: https://about.me/roshniahuja
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: my-custom-plugin
Domain Path: /languages
*/

// Hook into WordPress initialization
add_action( 'init', 'my_init' );

function my_init() {
    // Register shortcodes.
    add_shortcode( 'my_form', 'my_shortcode_form' );
    add_shortcode( 'my_list', 'my_shortcode_list' );
}

// Create custom table on plugin activation
register_activation_hook( __FILE__, 'maybe_create_my_table' );

function maybe_create_my_table() {
    global $wpdb;

    // Set up table name and charset collate
    $table_name      = $wpdb->prefix . 'things';
    $charset_collate = $wpdb->get_charset_collate();

    // Check if table exists using caching
    $table_exists = wp_cache_get( 'my_custom_table_exists', 'my_custom_plugin' );

    if ( false === $table_exists ) {
        // Table existence not found in cache, check the database
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        // Cache the result for future checks
        wp_cache_set( 'my_custom_table_exists', $table_exists, 'my_custom_plugin' );
    }

    // Create the table if it does not exist
    if ( $table_exists !== $table_name ) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Include necessary file for dbDelta function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Execute the SQL query using dbDelta
        dbDelta( $sql );
    }
}

function my_shortcode_form() {
    // Handle form submission
    if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        // Verify nonce
        $nonce = isset( $_POST['my_custom_nonce'] ) ? sanitize_text_field( $_POST['my_custom_nonce'] ) : '';
        if ( $nonce && wp_verify_nonce( $nonce, 'my_custom_form_nonce' ) ) {
            $name = isset( $_POST['thing_name'] ) ? sanitize_text_field( $_POST['thing_name'] ) : '';
            insert_data_to_my_table( $name );
        } else {
            // Nonce verification failed, handle the error or redirect as needed
            echo 'Nonce verification failed!';
        }
    }

    // Form HTML
    ob_start(); ?>
    <form method="POST">
        <label for="thing_name">Thing's Name:</label>
        <input type="text" name="thing_name" required>
        <?php wp_nonce_field( 'my_custom_form_nonce', 'my_custom_nonce' ); ?>
        <input type="submit" value="Submit">
    </form>
    <?php
    return ob_get_clean();
}

function my_shortcode_list() {
    // Handle search form submission
    if ( isset( $_GET['search'] ) ) {
        $nonce = isset( $_GET['my_custom_nonce'] ) ? sanitize_text_field( $_GET['my_custom_nonce'] ) : '';
        // Verify nonce for search form
        if ( $nonce && wp_verify_nonce( $nonce, 'my_custom_form_nonce' ) ) {
            $search = sanitize_text_field( $_GET['search'] );
            $data   = get_my_table_data( $search );
        } else {
            // Nonce verification failed, handle the error or redirect as needed
            echo 'Nonce verification failed for search form!';
        }
    } else {
        // If no search term, get all data
        $data = get_my_table_data();
    }

    // Display search form
    echo '<form method="get">';
    echo '<label for="search">Search:</label>';
    echo '<input type="text" name="search" value="' . esc_attr( isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '' ) . '">';
    // Include nonce field for search form
    wp_nonce_field( 'my_custom_form_nonce', 'my_custom_nonce' );
    echo '<input type="submit" value="Search">';
    echo '</form>';

    // Display data in a table
    ob_start();
    ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $data as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $row->id ); ?></td>
                    <td><?php echo esc_html( $row->name ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

function get_my_table_data( $search = '' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'things';

    // Construct the SQL query
    $sql = "SELECT * FROM $table_name";

    // Add search condition if a search term is provided
    if ( ! empty( $search ) ) {
        $sql .= $wpdb->prepare( ' WHERE name LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
    }

    // Use caching for the results with JSON
    $cache_key = 'my_custom_table_data_' . md5( $sql . wp_json_encode( $search ) );
    $results   = wp_cache_get( $cache_key, 'my_custom_plugin' );

    if ( false === $results ) {
        // Results not found in cache, query the database
        $results = $wpdb->get_results( $wpdb->prepare( $sql ) );

        // Cache the results for future calls
        wp_cache_set( $cache_key, $results, 'my_custom_plugin' );
    }

    return $results;
}

function insert_data_to_my_table( $name ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'things';

    // Check if the name is not empty
    if ( ! empty( $name ) ) {
        $nonce = isset( $_POST['my_custom_nonce'] ) ? sanitize_text_field( $_POST['my_custom_nonce'] ) : '';
        // Verify nonce for search form
        if ( $nonce && wp_verify_nonce( $nonce, 'my_custom_form_nonce' ) ) {
            // Insert data into the custom table
            $wpdb->insert( $table_name, array( 'name' => $name ) );
        } else {
            // Nonce verification failed, handle the error or redirect as needed
            echo 'Nonce verification failed for insert_data_to_my_table!';
            // You might want to redirect the user or display an error message
        }
    }
}

// Bonus: Extend WordPress REST API
function my_register_custom_endpoints() {
    register_rest_route(
        'my-custom-plugin/v1',
        '/insert',
        array(
            'methods'             => 'POST',
            'callback'            => 'my_custom_insert_data_to_table_rest',
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
        )
    );
    register_rest_route(
        'my-custom-plugin/v1',
        '/select',
        array(
            'methods'             => 'GET',
            'callback'            => 'my_custom_get_table_data_rest',
            'permission_callback' => function () {
                return current_user_can( 'read' );
            },
        )
    );
}

function my_custom_insert_data_to_table_rest( $data ) {
    if ( isset( $data['name'] ) ) {
        $name = sanitize_text_field( $data['name'] );
        my_insert_data_to_table( $name );
        return array( 'message' => 'Data inserted successfully.' );
    }
    return array( 'message' => 'Name not provided.' );
}

function my_custom_get_table_data_rest() {
    $data = my_get_table_data();
    return $data;
}
