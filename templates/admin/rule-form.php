<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1><?php
        if ( $is_edit ) {
            esc_html_e( '編輯分潤規則', 'ccm' );
        } elseif ( ! empty( $is_copy ) ) {
            esc_html_e( '複製分潤規則', 'ccm' );
        } else {
            esc_html_e( '新增分潤規則', 'ccm' );
        }
    ?></h1>

    <?php if ( ! empty( $is_copy ) && empty( $error ) && empty( $message ) ) : ?>
        <div class="notice notice-info"><p><?php esc_html_e( '已複製商品分潤設定，請選擇要套用的夥伴和折扣碼。', 'ccm' ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'ccm_save_rules', 'ccm_save_rules_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="partner_id"><?php esc_html_e( '夥伴', 'ccm' ); ?> <span class="required">*</span></label></th>
                <td>
                    <select id="partner_id" name="partner_id" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                        <option value=""><?php esc_html_e( '— 選擇夥伴 —', 'ccm' ); ?></option>
                        <?php foreach ( $partners as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $edit_partner_id, $p->id ); ?>>
                                <?php echo esc_html( $p->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( $is_edit ) : ?>
                        <input type="hidden" name="partner_id" value="<?php echo esc_attr( $edit_partner_id ); ?>">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="coupon_search"><?php esc_html_e( '折扣碼', 'ccm' ); ?> <span class="required">*</span></label></th>
                <td>
                    <?php if ( $is_edit ) :
                        $coupon_post = get_post( $edit_coupon_id );
                    ?>
                        <code style="font-size:14px;"><?php echo esc_html( $coupon_post ? strtoupper( $coupon_post->post_title ) : '#' . $edit_coupon_id ); ?></code>
                        <input type="hidden" name="coupon_id" value="<?php echo esc_attr( $edit_coupon_id ); ?>">
                    <?php else : ?>
                        <input type="text" id="coupon_search" class="regular-text ccm-autocomplete" data-type="coupons"
                               placeholder="<?php esc_attr_e( '點擊選擇或輸入折扣碼...', 'ccm' ); ?>" autocomplete="off">
                        <input type="hidden" id="coupon_id" name="coupon_id" value="">
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( '商品分潤設定', 'ccm' ); ?></h2>
        <p class="description"><?php esc_html_e( '設定各商品的分潤金額。「其他商品（預設）」適用於未特別指定的所有商品。', 'ccm' ); ?></p>

        <table class="widefat ccm-rules-table" id="ccm-rules-table">
            <thead>
                <tr>
                    <th style="width:50%;"><?php esc_html_e( '商品', 'ccm' ); ?></th>
                    <th style="width:30%;"><?php esc_html_e( '分潤金額', 'ccm' ); ?></th>
                    <th style="width:20%;"><?php esc_html_e( '操作', 'ccm' ); ?></th>
                </tr>
            </thead>
            <tbody id="ccm-rules-body">
                <?php
                // Separate default rule from product-specific rules
                $default_amount = 0;
                $product_rules  = [];
                if ( ! empty( $existing_rules ) ) {
                    foreach ( $existing_rules as $rule ) {
                        if ( 0 === (int) $rule->product_id ) {
                            $default_amount = (int) $rule->commission_amount;
                        } else {
                            $product_rules[] = $rule;
                        }
                    }
                }
                ?>
                <!-- Default row (always shown) -->
                <tr class="ccm-rule-row">
                    <td>
                        <em><?php esc_html_e( '其他商品（預設）', 'ccm' ); ?></em>
                        <input type="hidden" name="product_ids[]" value="0">
                    </td>
                    <td>
                        <span>NT$</span>
                        <input type="number" name="amounts[]" step="1" min="0"
                               value="<?php echo esc_attr( $default_amount ); ?>" style="width:120px;" placeholder="0">
                        <p class="description" style="margin:4px 0 0;font-size:11px;color:#666;"><?php esc_html_e( '填 0 表示未指定的商品不分潤', 'ccm' ); ?></p>
                    </td>
                    <td></td>
                </tr>
                <?php foreach ( $product_rules as $rule ) :
                    $product      = wc_get_product( $rule->product_id );
                    $display_name = '';
                    if ( $product ) {
                        if ( $product->is_type( 'variation' ) || $product->is_type( 'subscription_variation' ) ) {
                            $parent      = wc_get_product( $product->get_parent_id() );
                            $parent_name = $parent ? $parent->get_name() : '#' . $product->get_parent_id();
                            $attrs       = $product->get_variation_attributes();
                            $attr_str    = implode( ', ', array_filter( $attrs ) );
                            $display_name = $parent_name . ( $attr_str ? ' — ' . $attr_str : ' — 變化 #' . $product->get_id() );
                        } else {
                            $display_name = $product->get_name();
                        }
                    }
                ?>
                    <tr class="ccm-rule-row">
                        <td>
                            <input type="text" class="regular-text ccm-autocomplete" data-type="products"
                                   value="<?php echo esc_attr( $display_name ); ?>"
                                   placeholder="<?php esc_attr_e( '搜尋商品...', 'ccm' ); ?>">
                            <input type="hidden" name="product_ids[]" value="<?php echo esc_attr( $rule->product_id ); ?>">
                        </td>
                        <td>
                            <span>NT$</span>
                            <input type="number" name="amounts[]" step="1" min="0"
                                   value="<?php echo esc_attr( (int) $rule->commission_amount ); ?>" style="width:120px;" required>
                        </td>
                        <td>
                            <button type="button" class="button ccm-remove-row"><?php esc_html_e( '移除', 'ccm' ); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:12px;">
            <button type="button" class="button" id="ccm-add-product-row">+ <?php esc_html_e( '新增指定商品分潤', 'ccm' ); ?></button>
        </p>

        <!-- Template for new product row -->
        <template id="ccm-product-row-template">
            <tr class="ccm-rule-row">
                <td>
                    <input type="text" class="regular-text ccm-autocomplete" data-type="products"
                           placeholder="<?php esc_attr_e( '搜尋商品...', 'ccm' ); ?>">
                    <input type="hidden" name="product_ids[]" value="">
                </td>
                <td>
                    <span>NT$</span>
                    <input type="number" name="amounts[]" step="1" min="0" value="" style="width:120px;" required>
                </td>
                <td>
                    <button type="button" class="button ccm-remove-row"><?php esc_html_e( '移除', 'ccm' ); ?></button>
                </td>
            </tr>
        </template>

        <div style="margin-top:20px;">
            <?php submit_button( $is_edit ? __( '更新規則', 'ccm' ) : __( '儲存規則', 'ccm' ), 'primary', 'submit', false ); ?>
            &nbsp;<a href="<?php echo esc_url( admin_url( 'admin.php?page=ccm-rules' ) ); ?>" class="button"><?php esc_html_e( '返回列表', 'ccm' ); ?></a>
        </div>
    </form>
</div>
