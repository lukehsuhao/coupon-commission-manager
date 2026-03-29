<?php

namespace CouponCommissionManager\Admin;

use CouponCommissionManager\Admin\Pages\DashboardPage;
use CouponCommissionManager\Admin\Pages\PartnersPage;
use CouponCommissionManager\Admin\Pages\RulesPage;
use CouponCommissionManager\Admin\Pages\LogsPage;
use CouponCommissionManager\Admin\Pages\SettingsPage;
use CouponCommissionManager\Admin\Pages\CouponsPage;
use CouponCommissionManager\Admin\Pages\CouponEditPage;
use CouponCommissionManager\Admin\Pages\ApplicationsPage;
use CouponCommissionManager\Models\Application;

class Menu {

    public static function register(): void {
        add_action( 'admin_menu', [ self::class, 'add_menus' ] );
    }

    public static function add_menus(): void {
        add_menu_page(
            __( '分潤管理', 'ccm' ),
            __( '分潤管理', 'ccm' ),
            'manage_woocommerce',
            'ccm-dashboard',
            [ DashboardPage::class, 'render' ],
            'dashicons-money-alt',
            56
        );

        add_submenu_page(
            'ccm-dashboard',
            __( '總覽', 'ccm' ),
            __( '總覽', 'ccm' ),
            'manage_woocommerce',
            'ccm-dashboard',
            [ DashboardPage::class, 'render' ]
        );

        add_submenu_page(
            'ccm-dashboard',
            __( '夥伴管理', 'ccm' ),
            __( '夥伴管理', 'ccm' ),
            'manage_woocommerce',
            'ccm-partners',
            [ PartnersPage::class, 'render' ]
        );

        $pending_count = Application::count_by_status( 'pending' );
        $bubble        = $pending_count > 0
            ? sprintf( ' <span class="awaiting-mod">%d</span>', $pending_count )
            : '';

        add_submenu_page(
            'ccm-dashboard',
            __( '夥伴申請', 'ccm' ),
            __( '夥伴申請', 'ccm' ) . $bubble,
            'manage_woocommerce',
            'ccm-applications',
            [ ApplicationsPage::class, 'render' ]
        );

        add_submenu_page(
            'ccm-dashboard',
            __( '折扣碼', 'ccm' ),
            __( '折扣碼', 'ccm' ),
            'manage_woocommerce',
            'ccm-coupons',
            [ CouponsPage::class, 'render' ]
        );

        // Hidden page for coupon edit
        add_submenu_page(
            null, // Hidden from menu
            __( '編輯折扣碼', 'ccm' ),
            __( '編輯折扣碼', 'ccm' ),
            'manage_woocommerce',
            'ccm-coupon-edit',
            [ CouponEditPage::class, 'render' ]
        );

        add_submenu_page(
            'ccm-dashboard',
            __( '分潤規則', 'ccm' ),
            __( '分潤規則', 'ccm' ),
            'manage_woocommerce',
            'ccm-rules',
            [ RulesPage::class, 'render' ]
        );

        add_submenu_page(
            'ccm-dashboard',
            __( '分潤紀錄', 'ccm' ),
            __( '分潤紀錄', 'ccm' ),
            'manage_woocommerce',
            'ccm-logs',
            [ LogsPage::class, 'render' ]
        );

        add_submenu_page(
            'ccm-dashboard',
            __( '設定', 'ccm' ),
            __( '設定', 'ccm' ),
            'manage_woocommerce',
            'ccm-settings',
            [ SettingsPage::class, 'render' ]
        );
    }
}
