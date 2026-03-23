<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1><?php esc_html_e( '查看夥伴申請', 'ccm' ); ?></h1>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success"><p><?php echo wp_kses_post( $message ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <div style="display:flex;gap:24px;flex-wrap:wrap;">
        <!-- Application Details -->
        <div style="flex:1;min-width:400px;">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( '狀態', 'ccm' ); ?></th>
                    <td>
                        <?php
                        switch ( $application->status ) {
                            case 'pending':
                                echo '<span class="ccm-badge ccm-badge-unpaid">' . esc_html__( '待審核', 'ccm' ) . '</span>';
                                break;
                            case 'approved':
                                echo '<span class="ccm-badge ccm-badge-paid">' . esc_html__( '已通過', 'ccm' ) . '</span>';
                                break;
                            case 'rejected':
                                echo '<span class="ccm-badge ccm-badge-void">' . esc_html__( '已駁回', 'ccm' ) . '</span>';
                                break;
                        }
                        ?>
                    </td>
                </tr>
                <tr><th><?php esc_html_e( '姓名', 'ccm' ); ?></th><td><?php echo esc_html( $application->name ); ?></td></tr>
                <tr><th>Email</th><td><?php echo esc_html( $application->email ); ?></td></tr>
                <tr><th><?php esc_html_e( '電話', 'ccm' ); ?></th><td><?php echo esc_html( $application->phone ?: '—' ); ?></td></tr>
                <tr><th><?php esc_html_e( '公司名稱', 'ccm' ); ?></th><td><?php echo esc_html( $application->company_name ?: '—' ); ?></td></tr>
                <tr><th><?php esc_html_e( '統一編號', 'ccm' ); ?></th><td><?php echo esc_html( $application->tax_id ?: '—' ); ?></td></tr>
                <tr><th><?php esc_html_e( '銀行名稱', 'ccm' ); ?></th><td><?php echo esc_html( $application->bank_name ?: '—' ); ?></td></tr>
                <tr><th><?php esc_html_e( '銀行帳號', 'ccm' ); ?></th><td><?php echo esc_html( $application->bank_account ?: '—' ); ?></td></tr>
                <tr><th><?php esc_html_e( '戶名', 'ccm' ); ?></th><td><?php echo esc_html( $application->bank_account_name ?: '—' ); ?></td></tr>
                <tr><th><?php esc_html_e( '申請折扣碼', 'ccm' ); ?></th><td><code><?php echo esc_html( strtoupper( $application->desired_coupon_code ) ); ?></code></td></tr>
                <tr><th><?php esc_html_e( '備註', 'ccm' ); ?></th><td><?php echo nl2br( esc_html( $application->notes ?: '—' ) ); ?></td></tr>
                <tr><th><?php esc_html_e( '申請時間', 'ccm' ); ?></th><td><?php echo esc_html( $application->created_at ); ?></td></tr>
                <?php if ( $application->reviewed_at ) : ?>
                <tr><th><?php esc_html_e( '審核時間', 'ccm' ); ?></th><td><?php echo esc_html( $application->reviewed_at ); ?></td></tr>
                <tr><th><?php esc_html_e( '審核人', 'ccm' ); ?></th><td><?php
                    $reviewer = get_userdata( $application->reviewed_by );
                    echo esc_html( $reviewer ? $reviewer->display_name : '#' . $application->reviewed_by );
                ?></td></tr>
                <?php endif; ?>
                <?php if ( $application->admin_note ) : ?>
                <tr><th><?php esc_html_e( '管理員備註', 'ccm' ); ?></th><td><?php echo nl2br( esc_html( $application->admin_note ) ); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Review Actions (only if pending) -->
        <?php if ( 'pending' === $application->status ) : ?>
        <div style="flex:0 0 320px;">
            <div style="background:#fff;border:1px solid #c3c4c7;padding:16px;border-radius:4px;">
                <h3 style="margin-top:0;"><?php esc_html_e( '審核操作', 'ccm' ); ?></h3>
                <form method="post">
                    <?php wp_nonce_field( 'ccm_review_application', 'ccm_review_nonce' ); ?>

                    <p>
                        <label for="admin_note"><strong><?php esc_html_e( '管理員備註', 'ccm' ); ?></strong></label><br>
                        <textarea id="admin_note" name="admin_note" rows="4" style="width:100%;"></textarea>
                    </p>

                    <p style="display:flex;gap:8px;">
                        <button type="submit" name="ccm_do_approve" value="1" class="button button-primary">
                            <?php esc_html_e( '通過申請', 'ccm' ); ?>
                        </button>
                        <button type="submit" name="ccm_do_reject" value="1" class="button"
                                onclick="return confirm('<?php echo esc_js( __( '確定要駁回此申請嗎？', 'ccm' ) ); ?>')"
                                style="color:#b32d2e;">
                            <?php esc_html_e( '駁回申請', 'ccm' ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:16px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ccm-applications' ) ); ?>" class="button"><?php esc_html_e( '返回列表', 'ccm' ); ?></a>
    </p>
</div>
