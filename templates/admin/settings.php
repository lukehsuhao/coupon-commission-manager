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

        <?php submit_button( __( '儲存設定', 'ccm' ) ); ?>
    </form>
</div>
