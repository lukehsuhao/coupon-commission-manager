<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php if ( $success ) : ?>
    <div class="ccm-apply-success">
        <div class="ccm-success-icon">✅</div>
        <h3><?php esc_html_e( '申請已送出！', 'ccm' ); ?></h3>
        <p><?php esc_html_e( '感謝您的申請，我們會盡快審核並以 Email 通知您結果。', 'ccm' ); ?></p>
    </div>
<?php else : ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="ccm-apply-error"><?php echo esc_html( $error ); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ccm-apply-form">
        <input type="hidden" name="action" value="ccm_submit_application">
        <?php wp_nonce_field( 'ccm_partner_apply', 'ccm_apply_nonce' ); ?>
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <!-- 基本資料 -->
        <div class="ccm-form-section ccm-section-basic">
            <h3><?php esc_html_e( '基本資料', 'ccm' ); ?></h3>
            <div class="ccm-form-grid">
                <div class="ccm-form-row">
                    <label for="ccm-name"><?php esc_html_e( '姓名', 'ccm' ); ?> <span class="ccm-required">*</span></label>
                    <input type="text" id="ccm-name" name="name" required
                           value="<?php echo esc_attr( $form_data['name'] ?? '' ); ?>">
                </div>
                <div class="ccm-form-row">
                    <label for="ccm-email">Email <span class="ccm-required">*</span></label>
                    <input type="email" id="ccm-email" name="email" required
                           value="<?php echo esc_attr( $form_data['email'] ?? '' ); ?>">
                </div>
                <div class="ccm-form-row">
                    <label for="ccm-phone"><?php esc_html_e( '電話', 'ccm' ); ?></label>
                    <input type="text" id="ccm-phone" name="phone"
                           value="<?php echo esc_attr( $form_data['phone'] ?? '' ); ?>">
                </div>
                <div class="ccm-form-row">
                    <label for="ccm-company"><?php esc_html_e( '公司名稱', 'ccm' ); ?></label>
                    <input type="text" id="ccm-company" name="company_name"
                           value="<?php echo esc_attr( $form_data['company_name'] ?? '' ); ?>">
                </div>
                <div class="ccm-form-row">
                    <label for="ccm-tax-id"><?php esc_html_e( '統一編號', 'ccm' ); ?></label>
                    <input type="text" id="ccm-tax-id" name="tax_id" maxlength="8"
                           value="<?php echo esc_attr( $form_data['tax_id'] ?? '' ); ?>">
                </div>
            </div>
        </div>

        <!-- 銀行帳戶資訊 -->
        <div class="ccm-form-section ccm-section-bank">
            <h3><?php esc_html_e( '銀行帳戶資訊', 'ccm' ); ?></h3>
            <div class="ccm-form-grid">
                <div class="ccm-form-row">
                    <label for="ccm-bank-name"><?php esc_html_e( '銀行名稱', 'ccm' ); ?></label>
                    <input type="text" id="ccm-bank-name" name="bank_name"
                           value="<?php echo esc_attr( $form_data['bank_name'] ?? '' ); ?>">
                </div>
                <div class="ccm-form-row">
                    <label for="ccm-bank-account-name"><?php esc_html_e( '戶名', 'ccm' ); ?></label>
                    <input type="text" id="ccm-bank-account-name" name="bank_account_name"
                           value="<?php echo esc_attr( $form_data['bank_account_name'] ?? '' ); ?>">
                </div>
                <div class="ccm-form-row ccm-full-width">
                    <label for="ccm-bank-account"><?php esc_html_e( '銀行帳號', 'ccm' ); ?></label>
                    <input type="text" id="ccm-bank-account" name="bank_account"
                           value="<?php echo esc_attr( $form_data['bank_account'] ?? '' ); ?>">
                </div>
            </div>
        </div>

        <!-- 折扣碼 -->
        <div class="ccm-form-section ccm-section-coupon">
            <h3><?php esc_html_e( '折扣碼', 'ccm' ); ?></h3>
            <div class="ccm-form-row">
                <label for="ccm-coupon-code"><?php esc_html_e( '想要的折扣碼', 'ccm' ); ?> <span class="ccm-required">*</span></label>
                <input type="text" id="ccm-coupon-code" name="desired_coupon_code" required
                       style="text-transform:uppercase; font-weight:600; letter-spacing:0.05em;"
                       placeholder="<?php esc_attr_e( '例如：MYCODE2026', 'ccm' ); ?>"
                       value="<?php echo esc_attr( $form_data['desired_coupon_code'] ?? '' ); ?>">
                <p class="ccm-field-desc"><?php esc_html_e( '請輸入您想使用的專屬折扣碼，僅限英文字母、數字和連字號。如該折扣碼已被使用，我們會為您調整。', 'ccm' ); ?></p>
            </div>
        </div>

        <!-- 備註 -->
        <div class="ccm-form-section ccm-section-notes">
            <h3><?php esc_html_e( '其他', 'ccm' ); ?></h3>
            <div class="ccm-form-row">
                <label for="ccm-notes"><?php esc_html_e( '備註', 'ccm' ); ?></label>
                <textarea id="ccm-notes" name="notes" rows="4" placeholder="<?php esc_attr_e( '有什麼想讓我們知道的嗎？', 'ccm' ); ?>"><?php echo esc_textarea( $form_data['notes'] ?? '' ); ?></textarea>
            </div>
        </div>

        <div class="ccm-form-submit">
            <?php
            $ccm_settings   = get_option( 'ccm_settings', [] );
            $button_color   = ! empty( $ccm_settings['apply_button_color'] ) ? $ccm_settings['apply_button_color'] : '#2563eb';
            ?>
            <button type="submit" style="background: <?php echo esc_attr( $button_color ); ?>;"><?php esc_html_e( '送出申請', 'ccm' ); ?></button>
        </div>
    </form>

<?php endif; ?>
