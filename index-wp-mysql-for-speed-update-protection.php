<?php
/**
 * Plugin Name: Index WP MySQL For Speed - Update Protection
 * Description: Prevents WordPress core updates from reverting database optimizations made by Index WP MySQL For Speed
 * Version: 1.0
 * Author: Edmund Turbin
 */

add_filter( 'dbdelta_queries', 'imfspeeed_protect_indexes', 10, 1 );

/**
 * Filters the dbDelta SQL queries to preserve Index WP MySQL For Speed customizations.
 *
 * @param string[] $queries An array of dbDelta SQL queries.
 * @return string[] Modified array of queries.
 * @since 1.0.0
 */
function imfspeeed_protect_indexes( $queries ) {
    // Look for the core update lock
    $lock_option = 'core_updater.lock';
    $lock_result = get_option( $lock_option );
    
    /* No lock found? We're not doing a core update, so bail */
    if ( ! $lock_result ) {
        return $queries;
    }
    
    // Check to see if the lock is still valid. If not, bail.
    if ( $lock_result < ( time() - ( 15 * MINUTE_IN_SECONDS ) ) ) {
        return $queries;
    }
    
    // We're in a core update, so filter the queries to preserve our customizations
    global $wpdb;
    
    // Get the current table structure for tables modified by Index WP MySQL For Speed
    $tables_to_protect = array(
        $wpdb->posts,
        $wpdb->postmeta,
        $wpdb->comments,
        $wpdb->commentmeta,
        $wpdb->options,
        $wpdb->termmeta,
        $wpdb->usermeta
    );
    
    foreach ( $queries as $table => $query ) {
        // Check if this query affects one of our protected tables
        foreach ( $tables_to_protect as $protected_table ) {
            if ( strpos( $query, "CREATE TABLE $protected_table" ) !== false ) {
                // Get the actual current structure of the table
                $actual_structure = $wpdb->get_results( "SHOW CREATE TABLE $protected_table", ARRAY_N );
                
                if ( ! empty( $actual_structure[0][1] ) ) {
                    // Replace the standard query with the actual current structure
                    // This prevents dbDelta from trying to "fix" our optimized indexes
                    $queries[ $table ] = $actual_structure[0][1] . ';';
                }
            }
        }
    }
    
    return $queries;
}