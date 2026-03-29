<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1><?php echo $is_edit ? esc_html__( '編輯折扣碼', 'ccm' ) : esc_html__( '新增折扣碼', 'ccm' ); ?></h1>

    <?php if ( $error ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>
    <?php if ( $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'ccm_save_coupon', 'ccm_coupon_nonce' ); ?>

        <!-- ===== 基本設定 ===== -->
        <div class="ccm-section">
            <h2><?php esc_html_e( '基本設定', 'ccm' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="coupon_code"><?php esc_html_e( '折扣碼', 'ccm' ); ?> <span style="color:red;">*</span></label></th>
                    <td>
                        <input type="text" id="coupon_code" name="coupon_code" class="regular-text" required
                               style="text-transform:uppercase;font-weight:600;font-size:16px;"
                               value="<?php echo esc_attr( $coupon ? strtoupper( $coupon->get_code() ) : '' ); ?>"
                               placeholder="例：SUMMER2026">
                    </td>
                </tr>
                <tr>
                    <th><label for="description"><?php esc_html_e( '說明', 'ccm' ); ?></label></th>
                    <td><textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $coupon ? $coupon->get_description() : '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="date_expires"><?php esc_html_e( '到期日', 'ccm' ); ?></label></th>
                    <td>
                        <input type="date" id="date_expires" name="date_expires"
                               value="<?php echo esc_attr( $coupon && $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : '' ); ?>">
                        <p class="description"><?php esc_html_e( '留空表示永不過期', 'ccm' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( '使用限制', 'ccm' ); ?></th>
                    <td>
                        <label style="margin-right:20px;">
                            <?php esc_html_e( '總使用次數', 'ccm' ); ?>
                            <input type="number" name="usage_limit" min="0" style="width:80px;"
                                   value="<?php echo esc_attr( $coupon ? $coupon->get_usage_limit() : 0 ); ?>">
                        </label>
                        <label>
                            <?php esc_html_e( '每人使用次數', 'ccm' ); ?>
                            <input type="number" name="usage_limit_per_user" min="0" style="width:80px;"
                                   value="<?php echo esc_attr( $coupon ? $coupon->get_usage_limit_per_user() : 0 ); ?>">
                        </label>
                        <p class="description"><?php esc_html_e( '0 = 不限制', 'ccm' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( '消費限制', 'ccm' ); ?></th>
                    <td>
                        <label style="margin-right:20px;">
                            <?php esc_html_e( '最低消費 NT$', 'ccm' ); ?>
                            <input type="number" name="minimum_amount" min="0" step="1" style="width:100px;"
                                   value="<?php echo esc_attr( $coupon ? $coupon->get_minimum_amount() : '' ); ?>">
                        </label>
                        <label>
                            <?php esc_html_e( '最高消費 NT$', 'ccm' ); ?>
                            <input type="number" name="maximum_amount" min="0" step="1" style="width:100px;"
                                   value="<?php echo esc_attr( $coupon ? $coupon->get_maximum_amount() : '' ); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( '其他', 'ccm' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="individual_use" value="1"
                                <?php checked( $coupon && $coupon->get_individual_use() ); ?>>
                            <?php esc_html_e( '個人使用（不可與其他折扣碼併用）', 'ccm' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_restrictions"><?php esc_html_e( '限定 Email', 'ccm' ); ?></label></th>
                    <td>
                        <textarea id="email_restrictions" name="email_restrictions" rows="2" class="large-text" placeholder="user@example.com, *@company.com"><?php echo esc_textarea( $coupon ? implode( ', ', $coupon->get_email_restrictions() ) : '' ); ?></textarea>
                        <p class="description"><?php esc_html_e( '以逗號分隔，支援萬用字元（*@gmail.com）。留空表示不限制。', 'ccm' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ===== 折扣金額設定 ===== -->
        <div class="ccm-section">
            <h2><?php esc_html_e( '折扣金額設定', 'ccm' ); ?></h2>
            <p class="description"><?php esc_html_e( '設定各商品的折扣金額。優先順序：指定變化 > 指定商品 > 指定分類 > 預設。', 'ccm' ); ?></p>

            <table class="widefat ccm-discount-table" id="ccm-standard-rules">
                <thead>
                    <tr>
                        <th style="width:35%;"><?php esc_html_e( '適用對象', 'ccm' ); ?></th>
                        <th style="width:25%;"><?php esc_html_e( '折扣類型', 'ccm' ); ?></th>
                        <th style="width:20%;"><?php esc_html_e( '金額', 'ccm' ); ?></th>
                        <th style="width:20%;"><?php esc_html_e( '操作', 'ccm' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ccm-standard-rules-body">
                    <?php
                    $standard_rules = $coupon_rules['standard'];
                    $has_default = false;
                    foreach ( $standard_rules as $rule ) :
                        if ( $rule->target_type === 'all' ) $has_default = true;
                    ?>
                        <tr class="ccm-discount-row">
                            <td>
                                <?php if ( $rule->target_type === 'all' ) : ?>
                                    <em><?php esc_html_e( '所有商品（預設）', 'ccm' ); ?></em>
                                    <input type="hidden" name="rule_standard_target_type[]" value="all">
                                    <input type="hidden" name="rule_standard_target_id[]" value="0">
                                <?php else : ?>
                                    <select name="rule_standard_target_type[]" class="ccm-target-type-select" style="width:100px;">
                                        <option value="product" <?php selected( $rule->target_type, 'product' ); ?>><?php esc_html_e( '商品', 'ccm' ); ?></option>
                                        <option value="variation" <?php selected( $rule->target_type, 'variation' ); ?>><?php esc_html_e( '變化', 'ccm' ); ?></option>
                                        <option value="category" <?php selected( $rule->target_type, 'category' ); ?>><?php esc_html_e( '分類', 'ccm' ); ?></option>
                                    </select>
                                    <?php
                                    $display_name = '';
                                    if ( $rule->target_type === 'category' ) {
                                        $term = get_term( $rule->target_id, 'product_cat' );
                                        $display_name = $term ? $term->name : '#' . $rule->target_id;
                                    } else {
                                        $product = wc_get_product( $rule->target_id );
                                        if ( $product ) {
                                            if ( $product->is_type('variation') || $product->is_type('subscription_variation') ) {
                                                $parent = wc_get_product( $product->get_parent_id() );
                                                $attrs = implode(', ', array_filter($product->get_variation_attributes()));
                                                $display_name = ($parent ? $parent->get_name() : '') . ($attrs ? ' — ' . $attrs : '');
                                            } else {
                                                $display_name = $product->get_name();
                                            }
                                        }
                                    }
                                    ?>
                                    <input type="text" class="ccm-autocomplete" data-type="<?php echo $rule->target_type === 'category' ? 'categories' : 'products'; ?>"
                                           value="<?php echo esc_attr( $display_name ); ?>" style="width:calc(100% - 110px);">
                                    <input type="hidden" name="rule_standard_target_id[]" value="<?php echo esc_attr( $rule->target_id ); ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <select name="rule_standard_discount_type[]">
                                    <option value="fixed" <?php selected( $rule->discount_type, 'fixed' ); ?>><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                                    <option value="percent" <?php selected( $rule->discount_type, 'percent' ); ?>><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="rule_standard_amount[]" step="1" min="0"
                                       value="<?php echo esc_attr( $rule->discount_amount ); ?>" style="width:100px;">
                            </td>
                            <td>
                                <?php if ( $rule->target_type !== 'all' ) : ?>
                                    <button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ( ! $has_default ) : ?>
                        <tr class="ccm-discount-row">
                            <td>
                                <em><?php esc_html_e( '所有商品（預設）', 'ccm' ); ?></em>
                                <input type="hidden" name="rule_standard_target_type[]" value="all">
                                <input type="hidden" name="rule_standard_target_id[]" value="0">
                            </td>
                            <td>
                                <select name="rule_standard_discount_type[]">
                                    <option value="fixed"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                                    <option value="percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="rule_standard_amount[]" step="1" min="0" value="" style="width:100px;" placeholder="0">
                            </td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="margin-top:12px;">
                <button type="button" class="button" id="ccm-add-product-discount">+ <?php esc_html_e( '新增指定商品/變化折扣', 'ccm' ); ?></button>
                <button type="button" class="button" id="ccm-add-category-discount">+ <?php esc_html_e( '新增指定分類折扣', 'ccm' ); ?></button>
            </p>
        </div>

        <!-- ===== 訂閱折扣設定 ===== -->
        <?php if ( $has_wcs ) : ?>
        <div class="ccm-section">
            <h2><?php esc_html_e( '訂閱折扣設定', 'ccm' ); ?></h2>
            <p class="description"><?php esc_html_e( '針對 WooCommerce Subscriptions 訂閱商品，可分別設定開通費和續訂費的折扣。', 'ccm' ); ?></p>

            <?php
            // Helper to render subscription rule rows
            function ccm_render_sub_rules( $rules, $section, $fee_type, $pct_type ) {
            foreach ( $rules as $rule ) :
                $display_name = '';
                if ( $rule->target_type !== 'all' ) {
                    if ( $rule->target_type === 'category' ) {
                        $term = get_term( $rule->target_id, 'product_cat' );
                        $display_name = $term ? $term->name : '#' . $rule->target_id;
                    } else {
                        $product = wc_get_product( $rule->target_id );
                        if ( $product ) {
                            if ( $product->is_type('variation') || $product->is_type('subscription_variation') ) {
                                $parent = wc_get_product( $product->get_parent_id() );
                                $attrs = implode(', ', array_filter($product->get_variation_attributes()));
                                $display_name = ($parent ? $parent->get_name() : '') . ($attrs ? ' — ' . $attrs : '');
                            } else {
                                $display_name = $product->get_name();
                            }
                        }
                    }
                }
            ?>
                <tr class="ccm-discount-row">
                    <td>
                        <?php if ( $rule->target_type === 'all' ) : ?>
                            <em><?php esc_html_e( '所有訂閱商品（預設）', 'ccm' ); ?></em>
                            <input type="hidden" name="rule_<?php echo $section; ?>_target_type[]" value="all">
                            <input type="hidden" name="rule_<?php echo $section; ?>_target_id[]" value="0">
                        <?php else : ?>
                            <select name="rule_<?php echo $section; ?>_target_type[]" class="ccm-target-type-select" style="width:100px;">
                                <option value="product" <?php selected( $rule->target_type, 'product' ); ?>><?php esc_html_e( '商品', 'ccm' ); ?></option>
                                <option value="variation" <?php selected( $rule->target_type, 'variation' ); ?>><?php esc_html_e( '變化', 'ccm' ); ?></option>
                                <option value="category" <?php selected( $rule->target_type, 'category' ); ?>><?php esc_html_e( '分類', 'ccm' ); ?></option>
                            </select>
                            <input type="text" class="ccm-autocomplete" data-type="<?php echo $rule->target_type === 'category' ? 'categories' : 'products'; ?>"
                                   value="<?php echo esc_attr( $display_name ); ?>" style="width:calc(100% - 110px);">
                            <input type="hidden" name="rule_<?php echo $section; ?>_target_id[]" value="<?php echo esc_attr( $rule->target_id ); ?>">
                        <?php endif; ?>
                    </td>
                    <td>
                        <select name="rule_<?php echo $section; ?>_discount_type[]">
                            <option value="<?php echo $fee_type; ?>" <?php selected( $rule->discount_type, $fee_type ); ?>><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                            <option value="<?php echo $pct_type; ?>" <?php selected( $rule->discount_type, $pct_type ); ?>><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="rule_<?php echo $section; ?>_amount[]" step="1" min="0"
                               value="<?php echo esc_attr( $rule->discount_amount ); ?>" style="width:100px;">
                    </td>
                    <td>
                        <?php if ( $rule->target_type !== 'all' ) : ?>
                            <button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; } ?>

            <h3><?php esc_html_e( '開通費折扣（Sign-up Fee）', 'ccm' ); ?></h3>
            <table class="widefat ccm-discount-table" id="ccm-signup-rules">
                <thead>
                    <tr>
                        <th style="width:35%;"><?php esc_html_e( '適用對象', 'ccm' ); ?></th>
                        <th style="width:25%;"><?php esc_html_e( '折扣類型', 'ccm' ); ?></th>
                        <th style="width:20%;"><?php esc_html_e( '金額', 'ccm' ); ?></th>
                        <th style="width:20%;"><?php esc_html_e( '操作', 'ccm' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ccm-signup-rules-body">
                    <?php
                    $signup_has_default = false;
                    foreach ( $coupon_rules['signup'] as $r ) { if ( $r->target_type === 'all' ) $signup_has_default = true; }
                    if ( ! $signup_has_default ) :
                    ?>
                    <tr class="ccm-discount-row">
                        <td>
                            <em><?php esc_html_e( '所有訂閱商品（預設）', 'ccm' ); ?></em>
                            <input type="hidden" name="rule_signup_target_type[]" value="all">
                            <input type="hidden" name="rule_signup_target_id[]" value="0">
                        </td>
                        <td>
                            <select name="rule_signup_discount_type[]">
                                <option value="sign_up_fee"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                                <option value="sign_up_percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                            </select>
                        </td>
                        <td><input type="number" name="rule_signup_amount[]" step="1" min="0" value="" style="width:100px;" placeholder="0"></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                    <?php ccm_render_sub_rules( $coupon_rules['signup'], 'signup', 'sign_up_fee', 'sign_up_percent' ); ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button ccm-add-sub-product-rule" data-section="signup">+ <?php esc_html_e( '新增指定商品開通費折扣', 'ccm' ); ?></button>
            </p>

            <h3><?php esc_html_e( '續訂費折扣（Recurring Fee）', 'ccm' ); ?></h3>
            <table class="widefat ccm-discount-table" id="ccm-recurring-rules">
                <thead>
                    <tr>
                        <th style="width:35%;"><?php esc_html_e( '適用對象', 'ccm' ); ?></th>
                        <th style="width:25%;"><?php esc_html_e( '折扣類型', 'ccm' ); ?></th>
                        <th style="width:20%;"><?php esc_html_e( '金額', 'ccm' ); ?></th>
                        <th style="width:20%;"><?php esc_html_e( '操作', 'ccm' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ccm-recurring-rules-body">
                    <?php
                    $recurring_has_default = false;
                    foreach ( $coupon_rules['recurring'] as $r ) { if ( $r->target_type === 'all' ) $recurring_has_default = true; }
                    if ( ! $recurring_has_default ) :
                    ?>
                    <tr class="ccm-discount-row">
                        <td>
                            <em><?php esc_html_e( '所有訂閱商品（預設）', 'ccm' ); ?></em>
                            <input type="hidden" name="rule_recurring_target_type[]" value="all">
                            <input type="hidden" name="rule_recurring_target_id[]" value="0">
                        </td>
                        <td>
                            <select name="rule_recurring_discount_type[]">
                                <option value="recurring_fee"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                                <option value="recurring_percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                            </select>
                        </td>
                        <td><input type="number" name="rule_recurring_amount[]" step="1" min="0" value="" style="width:100px;" placeholder="0"></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                    <?php ccm_render_sub_rules( $coupon_rules['recurring'], 'recurring', 'recurring_fee', 'recurring_percent' ); ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button ccm-add-sub-product-rule" data-section="recurring">+ <?php esc_html_e( '新增指定商品續訂費折扣', 'ccm' ); ?></button>
            </p>
        </div>
        <?php endif; ?>

        <!-- Templates for dynamic rows -->
        <template id="ccm-product-discount-template">
            <tr class="ccm-discount-row">
                <td>
                    <select name="rule_standard_target_type[]" class="ccm-target-type-select" style="width:100px;">
                        <option value="product"><?php esc_html_e( '商品', 'ccm' ); ?></option>
                        <option value="variation"><?php esc_html_e( '變化', 'ccm' ); ?></option>
                    </select>
                    <input type="text" class="ccm-autocomplete" data-type="products" placeholder="<?php esc_attr_e( '搜尋商品...', 'ccm' ); ?>" style="width:calc(100% - 110px);">
                    <input type="hidden" name="rule_standard_target_id[]" value="">
                </td>
                <td>
                    <select name="rule_standard_discount_type[]">
                        <option value="fixed"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                        <option value="percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                    </select>
                </td>
                <td><input type="number" name="rule_standard_amount[]" step="1" min="0" style="width:100px;"></td>
                <td><button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button></td>
            </tr>
        </template>

        <template id="ccm-category-discount-template">
            <tr class="ccm-discount-row">
                <td>
                    <span style="display:inline-block;width:100px;color:#666;"><?php esc_html_e( '分類', 'ccm' ); ?></span>
                    <input type="text" class="ccm-autocomplete" data-type="categories" placeholder="<?php esc_attr_e( '搜尋分類...', 'ccm' ); ?>" style="width:calc(100% - 110px);">
                    <input type="hidden" name="rule_standard_target_type[]" value="category">
                    <input type="hidden" name="rule_standard_target_id[]" value="">
                </td>
                <td>
                    <select name="rule_standard_discount_type[]">
                        <option value="fixed"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                        <option value="percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                    </select>
                </td>
                <td><input type="number" name="rule_standard_amount[]" step="1" min="0" style="width:100px;"></td>
                <td><button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button></td>
            </tr>
        </template>

        <!-- Subscription default templates -->
        <template id="ccm-sub-rule-template-signup">
            <tr class="ccm-discount-row">
                <td>
                    <em><?php esc_html_e( '所有訂閱商品（預設）', 'ccm' ); ?></em>
                    <input type="hidden" name="rule_signup_target_type[]" value="all">
                    <input type="hidden" name="rule_signup_target_id[]" value="0">
                </td>
                <td>
                    <select name="rule_signup_discount_type[]">
                        <option value="sign_up_fee"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                        <option value="sign_up_percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                    </select>
                </td>
                <td><input type="number" name="rule_signup_amount[]" step="1" min="0" style="width:100px;"></td>
                <td><button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button></td>
            </tr>
        </template>

        <template id="ccm-sub-rule-template-recurring">
            <tr class="ccm-discount-row">
                <td>
                    <em><?php esc_html_e( '所有訂閱商品（預設）', 'ccm' ); ?></em>
                    <input type="hidden" name="rule_recurring_target_type[]" value="all">
                    <input type="hidden" name="rule_recurring_target_id[]" value="0">
                </td>
                <td>
                    <select name="rule_recurring_discount_type[]">
                        <option value="recurring_fee"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                        <option value="recurring_percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                    </select>
                </td>
                <td><input type="number" name="rule_recurring_amount[]" step="1" min="0" style="width:100px;"></td>
                <td><button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button></td>
            </tr>
        </template>

        <!-- Subscription per-product templates -->
        <template id="ccm-sub-product-rule-template-signup">
            <tr class="ccm-discount-row">
                <td>
                    <select name="rule_signup_target_type[]" class="ccm-target-type-select" style="width:100px;">
                        <option value="product"><?php esc_html_e( '商品', 'ccm' ); ?></option>
                        <option value="variation"><?php esc_html_e( '變化', 'ccm' ); ?></option>
                        <option value="category"><?php esc_html_e( '分類', 'ccm' ); ?></option>
                    </select>
                    <input type="text" class="ccm-autocomplete" data-type="products" placeholder="<?php esc_attr_e( '搜尋商品...', 'ccm' ); ?>" style="width:calc(100% - 110px);">
                    <input type="hidden" name="rule_signup_target_id[]" value="">
                </td>
                <td>
                    <select name="rule_signup_discount_type[]">
                        <option value="sign_up_fee"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                        <option value="sign_up_percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                    </select>
                </td>
                <td><input type="number" name="rule_signup_amount[]" step="1" min="0" style="width:100px;"></td>
                <td><button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button></td>
            </tr>
        </template>

        <template id="ccm-sub-product-rule-template-recurring">
            <tr class="ccm-discount-row">
                <td>
                    <select name="rule_recurring_target_type[]" class="ccm-target-type-select" style="width:100px;">
                        <option value="product"><?php esc_html_e( '商品', 'ccm' ); ?></option>
                        <option value="variation"><?php esc_html_e( '變化', 'ccm' ); ?></option>
                        <option value="category"><?php esc_html_e( '分類', 'ccm' ); ?></option>
                    </select>
                    <input type="text" class="ccm-autocomplete" data-type="products" placeholder="<?php esc_attr_e( '搜尋商品...', 'ccm' ); ?>" style="width:calc(100% - 110px);">
                    <input type="hidden" name="rule_recurring_target_id[]" value="">
                </td>
                <td>
                    <select name="rule_recurring_discount_type[]">
                        <option value="recurring_fee"><?php esc_html_e( '固定金額 (NT$)', 'ccm' ); ?></option>
                        <option value="recurring_percent"><?php esc_html_e( '百分比 (%)', 'ccm' ); ?></option>
                    </select>
                </td>
                <td><input type="number" name="rule_recurring_amount[]" step="1" min="0" style="width:100px;"></td>
                <td><button type="button" class="button ccm-remove-discount-row"><?php esc_html_e( '移除', 'ccm' ); ?></button></td>
            </tr>
        </template>

        <div style="margin-top:24px;">
            <?php submit_button( $is_edit ? __( '更新折扣碼', 'ccm' ) : __( '建立折扣碼', 'ccm' ), 'primary', 'submit', false ); ?>
            &nbsp;<a href="<?php echo esc_url( admin_url( 'admin.php?page=ccm-coupons' ) ); ?>" class="button"><?php esc_html_e( '返回列表', 'ccm' ); ?></a>
        </div>
    </form>
</div>

<style>
    .ccm-section { background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 20px; border-radius: 4px; }
    .ccm-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .ccm-discount-table { margin-top: 12px; }
    .ccm-discount-table th { padding: 10px 12px; background: #f8f9fa; }
    .ccm-discount-table td { padding: 8px 12px; vertical-align: middle; }
    .ccm-discount-row select { vertical-align: middle; }
</style>
