<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1><?php echo $partner ? esc_html__( '編輯夥伴', 'ccm' ) : esc_html__( '新增夥伴', 'ccm' ); ?></h1>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'ccm_save_partner', 'ccm_save_partner_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="name"><?php esc_html_e( '姓名', 'ccm' ); ?> <span class="required">*</span></label></th>
                <td><input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr( $partner->name ?? '' ); ?>" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="email"><?php esc_html_e( 'Email', 'ccm' ); ?></label></th>
                <td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr( $partner->email ?? '' ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="phone"><?php esc_html_e( '電話', 'ccm' ); ?></label></th>
                <td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr( $partner->phone ?? '' ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="bank_name"><?php esc_html_e( '銀行名稱', 'ccm' ); ?></label></th>
                <td><input type="text" id="bank_name" name="bank_name" class="regular-text" value="<?php echo esc_attr( $partner->bank_name ?? '' ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="bank_account"><?php esc_html_e( '銀行帳號', 'ccm' ); ?></label></th>
                <td><input type="text" id="bank_account" name="bank_account" class="regular-text" value="<?php echo esc_attr( $partner->bank_account ?? '' ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="bank_account_name"><?php esc_html_e( '戶名', 'ccm' ); ?></label></th>
                <td><input type="text" id="bank_account_name" name="bank_account_name" class="regular-text" value="<?php echo esc_attr( $partner->bank_account_name ?? '' ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="notes"><?php esc_html_e( '備註', 'ccm' ); ?></label></th>
                <td><textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $partner->notes ?? '' ); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '狀態', 'ccm' ); ?></th>
                <td>
                    <label><input type="radio" name="status" value="active" <?php checked( $partner->status ?? 'active', 'active' ); ?>> <?php esc_html_e( '啟用', 'ccm' ); ?></label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="status" value="inactive" <?php checked( $partner->status ?? '', 'inactive' ); ?>> <?php esc_html_e( '停用', 'ccm' ); ?></label>
                </td>
            </tr>
        </table>

        <?php submit_button( $partner ? __( '更新夥伴', 'ccm' ) : __( '新增夥伴', 'ccm' ) ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ccm-partners' ) ); ?>" class="button"><?php esc_html_e( '返回列表', 'ccm' ); ?></a>
    </form>
</div>
