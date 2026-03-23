<?php

namespace CouponCommissionManager;

use CouponCommissionManager\Database\Schema;

class Activator {

    public static function activate(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_die(
                __( '此外掛需要 WooCommerce 才能運作。請先安裝並啟用 WooCommerce。', 'ccm' ),
                __( '外掛啟用失敗', 'ccm' ),
                array( 'back_link' => true )
            );
        }

        Schema::create_tables();
        update_option( 'ccm_db_version', CCM_VERSION );
    }
}
