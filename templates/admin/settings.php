<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1><?php esc_html_e( '分潤管理設定', 'ccm' ); ?></h1>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'ccm_save_settings', 'ccm_settings_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( '觸發時機', 'ccm' ); ?></th>
                <td>
                    <p><strong><?php esc_html_e( '訂單完成 (completed)', 'ccm' ); ?></strong> — <?php esc_html_e( '預設啟用，不可關閉', 'ccm' ); ?></p>
                    <label>
                        <input type="checkbox" name="trigger_on_processing" value="1"
                            <?php checked( ! empty( $settings['trigger_on_processing'] ) ); ?>>
                        <?php esc_html_e( '訂單處理中 (processing) 時也觸發分潤計算', 'ccm' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( '如果你的商品不需要出貨，可以啟用此選項讓分潤在付款後立即產生。', 'ccm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '訂閱續約', 'ccm' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="skip_renewal_orders" value="1"
                            <?php checked( ! empty( $settings['skip_renewal_orders'] ) ); ?>>
                        <?php esc_html_e( '訂閱商品只分潤首筆訂單，續約訂單不產生分潤', 'ccm' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( '適用於 WooCommerce Subscriptions。勾選後，自動續約產生的訂單將不會計算分潤。', 'ccm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '移除外掛時', 'ccm' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="delete_data_on_uninstall" value="1"
                            <?php checked( $delete_on_uninstall ); ?>>
                        <?php esc_html_e( '移除外掛時刪除所有分潤資料（夥伴、規則、紀錄）', 'ccm' ); ?>
                    </label>
                    <p class="description" style="color:#d63638;"><?php esc_html_e( '⚠ 此操作不可逆，請謹慎啟用。', 'ccm' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( '夥伴申請表單', 'ccm' ); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="apply_redirect_url"><?php esc_html_e( '申請後跳轉網址', 'ccm' ); ?></label></th>
                <td>
                    <input type="url" id="apply_redirect_url" name="apply_redirect_url" class="regular-text"
                           value="<?php echo esc_attr( $settings['apply_redirect_url'] ?? '' ); ?>"
                           placeholder="<?php echo esc_attr( home_url() ); ?>">
                    <p class="description"><?php esc_html_e( '夥伴送出申請後會跳轉到此網址。留空預設跳轉到首頁。網址會自動帶上 ?ccm_applied=1 參數，你可以在該頁面用此參數顯示感謝訊息。', 'ccm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="apply_button_color"><?php esc_html_e( '送出按鈕顏色', 'ccm' ); ?></label></th>
                <td>
                    <input type="color" id="apply_button_color" name="apply_button_color"
                           value="<?php echo esc_attr( $settings['apply_button_color'] ?? '#2563eb' ); ?>"
                           style="width:60px;height:36px;padding:2px;cursor:pointer;">
                    <span style="margin-left:8px;color:#666;"><?php echo esc_html( $settings['apply_button_color'] ?? '#2563eb' ); ?></span>
                    <p class="description"><?php esc_html_e( '前台申請表單「送出申請」按鈕的顏色。', 'ccm' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="admin_notification_email"><?php esc_html_e( '申請通知信箱', 'ccm' ); ?></label></th>
                <td>
                    <input type="email" id="admin_notification_email" name="admin_notification_email" class="regular-text"
                           value="<?php echo esc_attr( $settings['admin_notification_email'] ?? '' ); ?>"
                           placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                    <p class="description"><?php esc_html_e( '有人送出夥伴申請時，通知信會寄到此信箱。留空預設使用 WordPress 管理員信箱。', 'ccm' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( '通知信模板', 'ccm' ); ?></h2>
        <p class="description"><?php esc_html_e( '可用變數：{partner_name}、{email}、{coupon_code}、{site_name}、{site_url}。留空則使用預設模板。', 'ccm' ); ?></p>

        <?php
        $default_approval  = \CouponCommissionManager\Services\ApplicationService::default_approval_template();
        $default_rejection = \CouponCommissionManager\Services\ApplicationService::default_rejection_template();
        $current_approval  = $settings['email_approval_template'] ?? '';
        $current_rejection = $settings['email_rejection_template'] ?? '';
        ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="email_approval_template"><?php esc_html_e( '審核通過信', 'ccm' ); ?></label></th>
                <td>
                    <textarea id="email_approval_template" name="email_approval_template" rows="12" class="large-text"><?php
                        echo esc_textarea( ! empty( $current_approval ) ? $current_approval : $default_approval );
                    ?></textarea>
                    <p>
                        <button type="button" class="button button-small" onclick="document.getElementById('email_approval_template').value = <?php echo esc_attr( wp_json_encode( $default_approval ) ); ?>;"><?php esc_html_e( '還原為預設模板', 'ccm' ); ?></button>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="email_rejection_template"><?php esc_html_e( '審核不通過信', 'ccm' ); ?></label></th>
                <td>
                    <textarea id="email_rejection_template" name="email_rejection_template" rows="12" class="large-text"><?php
                        echo esc_textarea( ! empty( $current_rejection ) ? $current_rejection : $default_rejection );
                    ?></textarea>
                    <p>
                        <button type="button" class="button button-small" onclick="document.getElementById('email_rejection_template').value = <?php echo esc_attr( wp_json_encode( $default_rejection ) ); ?>;"><?php esc_html_e( '還原為預設模板', 'ccm' ); ?></button>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( '儲存設定', 'ccm' ) ); ?>
    </form>
</div>
