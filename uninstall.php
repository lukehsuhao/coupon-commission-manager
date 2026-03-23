<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$delete_data = get_option( 'ccm_delete_data_on_uninstall', false );

if ( $delete_data ) {
    require_once plugin_dir_path( __FILE__ ) . 'src/Database/Schema.php';

    \CouponCommissionManager\Database\Schema::drop_tables();

    delete_option( 'ccm_db_version' );
    delete_option( 'ccm_delete_data_on_uninstall' );
    delete_option( 'ccm_settings' );
}
