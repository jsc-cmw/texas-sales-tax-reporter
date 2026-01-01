<?php
/**
 * Plugin Name: Texas Sales Tax Reporter
 * Plugin URI: https://cardmachineworks.com
 * Description: Generate quarterly Texas sales tax reports from WooCommerce orders and email them automatically
 * Version: 1.3.0
 * Author: Card Machine Works
 * Author URI: https://cardmachineworks.com
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Texas_Sales_Tax_Reporter {

    private $plugin_name = 'Texas Sales Tax Reporter';

    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register AJAX handlers
        add_action('wp_ajax_generate_tx_tax_report', array($this, 'ajax_generate_report'));

        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Hook for scheduled reports
        add_action('tx_tax_quarterly_report', array($this, 'send_scheduled_report'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Texas Sales Tax Reporter',
            'TX Sales Tax',
            'manage_woocommerce',
            'texas-sales-tax-reporter',
            array($this, 'admin_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_texas-sales-tax-reporter') {
            return;
        }

        wp_enqueue_script('jquery');
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        // Get current quarter dates as defaults
        $current_quarter = $this->get_current_quarter_dates();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->plugin_name); ?></h1>
            
            <div class="notice notice-info" style="max-width: 800px;">
                <p><strong>Ready to file?</strong> 
                <a href="https://security.app.cpa.state.tx.us/public/login" target="_blank" class="button button-primary" style="margin-left: 10px;">
                    File Texas Sales Tax Return →
                </a>
                </p>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Generate Texas Sales Tax Report</h2>
                <p class="description">
                    <strong>Note:</strong> In Texas, sales tax is collected on both products and shipping charges for taxable items. 
                    This report includes the total tax collected (product tax + shipping tax).
                </p>
                
                <form id="tx-tax-report-form" method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('generate_tx_tax_report', 'tx_tax_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="start_date">Start Date</label>
                            </th>
                            <td>
                                <input type="date" 
                                       id="start_date" 
                                       name="start_date" 
                                       value="<?php echo esc_attr($current_quarter['start']); ?>" 
                                       required 
                                       style="width: 200px;">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="end_date">End Date</label>
                            </th>
                            <td>
                                <input type="date" 
                                       id="end_date" 
                                       name="end_date" 
                                       value="<?php echo esc_attr($current_quarter['end']); ?>" 
                                       required 
                                       style="width: 200px;">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="email_address">Email Address</label>
                            </th>
                            <td>
                                <input type="email" 
                                       id="email_address" 
                                       name="email_address" 
                                       value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                                       required 
                                       style="width: 300px;">
                                <p class="description">Report will be sent to this email address</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="report_format">Report Format</label>
                            </th>
                            <td>
                                <select id="report_format" name="report_format">
                                    <option value="summary">Summary Only (Total Taxable Sales & Tax)</option>
                                    <option value="detailed">Detailed (All Order Breakdowns)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="send_email">Send Email</label>
                            </th>
                            <td>
                                <input type="checkbox"
                                       id="send_email"
                                       name="send_email"
                                       value="1"
                                       checked>
                                <label for="send_email">Email report after generation</label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="generate-report-btn">
                            Generate Report
                        </button>
                        <span class="spinner" style="float: none; margin: 0 10px;"></span>
                    </p>
                </form>
                
                <div id="report-results" style="margin-top: 30px;"></div>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h3>Quick Quarter Selection</h3>
                <p style="margin-bottom: 10px;">
                    <label for="quarter_year" style="margin-right: 10px;"><strong>Year:</strong></label>
                    <select id="quarter_year" style="width: 100px; margin-right: 20px;">
                        <?php
                        $current_year = date('Y');
                        for ($y = $current_year; $y >= $current_year - 3; $y--) {
                            echo '<option value="' . $y . '">' . $y . '</option>';
                        }
                        ?>
                    </select>
                    <button type="button" class="button" onclick="setQuarter('Q1')">Q1 (Jan-Mar)</button>
                    <button type="button" class="button" onclick="setQuarter('Q2')">Q2 (Apr-Jun)</button>
                    <button type="button" class="button" onclick="setQuarter('Q3')">Q3 (Jul-Sep)</button>
                    <button type="button" class="button" onclick="setQuarter('Q4')">Q4 (Oct-Dec)</button>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#tx-tax-report-form').on('submit', function(e) {
                e.preventDefault();
                
                var $btn = $('#generate-report-btn');
                var $spinner = $('.spinner');
                var $results = $('#report-results');
                
                $btn.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.html('<div class="notice notice-info"><p>Generating report...</p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_tx_tax_report',
                        nonce: $('#tx_tax_nonce').val(),
                        start_date: $('#start_date').val(),
                        end_date: $('#end_date').val(),
                        email_address: $('#email_address').val(),
                        send_email: $('#send_email').is(':checked') ? 1 : 0,
                        report_format: $('#report_format').val()
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                        
                        if (response.success) {
                            $results.html(response.data.html);
                        } else {
                            $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                        $results.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
                    }
                });
            });
        });
        
        function setQuarter(quarter) {
            var year = document.getElementById('quarter_year').value;
            var startDate, endDate;

            switch(quarter) {
                case 'Q1':
                    startDate = year + '-01-01';
                    endDate = year + '-03-31';
                    break;
                case 'Q2':
                    startDate = year + '-04-01';
                    endDate = year + '-06-30';
                    break;
                case 'Q3':
                    startDate = year + '-07-01';
                    endDate = year + '-09-30';
                    break;
                case 'Q4':
                    startDate = year + '-10-01';
                    endDate = year + '-12-31';
                    break;
            }

            document.getElementById('start_date').value = startDate;
            document.getElementById('end_date').value = endDate;
        }
        </script>
        
        <style>
        #report-results table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        #report-results th,
        #report-results td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        #report-results th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        #report-results .summary-box {
            background: #f0f7ff;
            border: 2px solid #0073aa;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        #report-results .summary-box h3 {
            margin-top: 0;
            color: #0073aa;
        }
        #report-results .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        #report-results .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2em;
            padding-top: 15px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler to generate report
     */
    public function ajax_generate_report() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'generate_tx_tax_report')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Get parameters
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $email_address = sanitize_email($_POST['email_address']);
        $send_email = isset($_POST['send_email']) && $_POST['send_email'] == '1';
        $report_format = isset($_POST['report_format']) ? sanitize_text_field($_POST['report_format']) : 'summary';

        // Validate dates
        if (!strtotime($start_date) || !strtotime($end_date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
        }

        // Generate report
        $report_data = $this->generate_report($start_date, $end_date);

        // Generate HTML
        $html = $this->generate_report_html($report_data, $start_date, $end_date, $report_format);

        // Send email if requested
        if ($send_email && !empty($email_address)) {
            $email_sent = $this->send_report_email($report_data, $start_date, $end_date, $email_address, $report_format);

            if ($email_sent) {
                $html = '<div class="notice notice-success"><p>Report generated and emailed to ' . esc_html($email_address) . '</p></div>' . $html;
            } else {
                $html = '<div class="notice notice-warning"><p>Report generated but email failed to send. Please check your email settings.</p></div>' . $html;
            }
        } else {
            $html = '<div class="notice notice-success"><p>Report generated successfully</p></div>' . $html;
        }

        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Generate report data
     */
    private function generate_report($start_date, $end_date) {
        global $wpdb;

        // Adjust end date to include entire day
        $end_date_time = $end_date . ' 23:59:59';

        // Get orders with detailed breakdown, including refund amounts
        $query = $wpdb->prepare("
            SELECT
                p.ID as order_id,
                p.post_date as order_date,
                MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as order_total,
                MAX(CASE WHEN pm.meta_key = '_order_tax' THEN pm.meta_value END) as order_tax,
                MAX(CASE WHEN pm.meta_key = '_cart_tax' THEN pm.meta_value END) as cart_tax,
                MAX(CASE WHEN pm.meta_key = '_order_shipping' THEN pm.meta_value END) as shipping_cost,
                MAX(CASE WHEN pm.meta_key = '_order_shipping_tax' THEN pm.meta_value END) as shipping_tax,
                MAX(CASE WHEN pm.meta_key = '_shipping_city' THEN pm.meta_value END) as city,
                MAX(CASE WHEN pm.meta_key = '_shipping_postcode' THEN pm.meta_value END) as zip_code,
                MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) as customer_email,
                COALESCE((
                    SELECT SUM(refund_meta.meta_value)
                    FROM {$wpdb->posts} refund
                    INNER JOIN {$wpdb->postmeta} refund_meta ON refund.ID = refund_meta.post_id
                    WHERE refund.post_type = 'shop_order_refund'
                    AND refund.post_parent = p.ID
                    AND refund_meta.meta_key = '_refund_amount'
                ), 0) as refund_total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND p.post_date BETWEEN %s AND %s
                AND p.ID IN (
                    SELECT post_id
                    FROM {$wpdb->postmeta}
                    WHERE meta_key = '_shipping_state'
                    AND meta_value = 'TX'
                )
            GROUP BY p.ID
            ORDER BY p.post_date DESC
        ", $start_date, $end_date_time);

        $orders = $wpdb->get_results($query);

        // Calculate totals (accounting for refunds)
        $total_orders = count($orders);
        $total_order_value = 0;
        $total_sales_tax = 0;
        $total_cart_tax = 0;
        $total_shipping_cost = 0;
        $total_shipping_tax = 0;
        $total_refunds = 0;

        foreach ($orders as $order) {
            $refund_amount = floatval($order->refund_total);
            $order_total = floatval($order->order_total);
            $order_tax = floatval($order->order_tax);

            // Calculate refund ratio to proportionally reduce tax
            $refund_ratio = ($order_total > 0) ? ($refund_amount / $order_total) : 0;

            // Store net values on the order object for display
            $order->net_total = $order_total - $refund_amount;
            $order->net_tax = $order_tax * (1 - $refund_ratio);
            $order->has_refund = ($refund_amount > 0);

            $total_order_value += $order->net_total;
            $total_sales_tax += $order->net_tax;
            $total_cart_tax += floatval($order->cart_tax) * (1 - $refund_ratio);
            $total_shipping_cost += floatval($order->shipping_cost);
            $total_shipping_tax += floatval($order->shipping_tax) * (1 - $refund_ratio);
            $total_refunds += $refund_amount;
        }

        // Calculate taxable sales (total before tax)
        $total_taxable_sales = $total_order_value - $total_sales_tax;

        return array(
            'orders' => $orders,
            'total_orders' => $total_orders,
            'total_order_value' => $total_order_value,
            'total_taxable_sales' => $total_taxable_sales,
            'total_sales_tax' => $total_sales_tax,
            'total_cart_tax' => $total_cart_tax,
            'total_shipping_cost' => $total_shipping_cost,
            'total_shipping_tax' => $total_shipping_tax,
            'total_refunds' => $total_refunds,
            'start_date' => $start_date,
            'end_date' => $end_date
        );
    }
    
    /**
     * Generate HTML for report display
     */
    private function generate_report_html($report_data, $start_date, $end_date, $report_format = 'summary') {
        ob_start();

        if ($report_format === 'summary') {
            // Simplified summary format for comptroller
            ?>
            <div class="summary-box">
                <h3>Texas Sales Tax Report - Summary</h3>
                <p><strong>Period:</strong> <?php echo esc_html(date('F j, Y', strtotime($start_date))); ?> to <?php echo esc_html(date('F j, Y', strtotime($end_date))); ?></p>

                <div class="summary-item">
                    <span>Total Orders to Texas:</span>
                    <span><strong><?php echo number_format($report_data['total_orders']); ?></strong></span>
                </div>
                <div class="summary-item" style="border-top: 2px solid #ddd; padding-top: 15px; margin-top: 15px;">
                    <span>Total Taxable Sales:</span>
                    <span style="color: #0073aa;"><strong>$<?php echo number_format($report_data['total_taxable_sales'], 2); ?></strong></span>
                </div>
                <div class="summary-item" style="border-top: 3px solid #0073aa; padding-top: 15px;">
                    <span>Total Sales Tax to Remit:</span>
                    <span style="color: #0073aa;"><strong>$<?php echo number_format($report_data['total_sales_tax'], 2); ?></strong></span>
                </div>
            </div>

            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin: 20px 0;">
                <p style="margin: 0;"><strong>Note:</strong> Report these figures to Texas Comptroller. Sales tax includes tax on both products and shipping.</p>
            </div>
            <?php
        } else {
            // Detailed format with all breakdowns
            ?>
            <div class="summary-box">
                <h3>Texas Sales Tax Report - Detailed</h3>
                <p><strong>Period:</strong> <?php echo esc_html(date('F j, Y', strtotime($start_date))); ?> to <?php echo esc_html(date('F j, Y', strtotime($end_date))); ?></p>

                <div class="summary-item">
                    <span>Total Orders to Texas:</span>
                    <span><strong><?php echo number_format($report_data['total_orders']); ?></strong></span>
                </div>
                <div class="summary-item">
                    <span>Total Order Value (w/ tax):</span>
                    <span><strong>$<?php echo number_format($report_data['total_order_value'], 2); ?></strong></span>
                </div>
                <?php if ($report_data['total_refunds'] > 0): ?>
                <div class="summary-item" style="background-color: #ffe6e6; margin: 10px -20px; padding: 10px 20px;">
                    <span>Total Refunds:</span>
                    <span style="color: #d63638;">-$<?php echo number_format($report_data['total_refunds'], 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-item" style="background-color: #f9f9f9; margin: 10px -20px; padding: 10px 20px;">
                    <span>Tax on Products (net):</span>
                    <span>$<?php echo number_format($report_data['total_cart_tax'], 2); ?></span>
                </div>
                <div class="summary-item" style="background-color: #f9f9f9; margin: 10px -20px 0; padding: 10px 20px;">
                    <span>Total Shipping Charged:</span>
                    <span>$<?php echo number_format($report_data['total_shipping_cost'], 2); ?></span>
                </div>
                <div class="summary-item" style="background-color: #f9f9f9; margin: 0 -20px 10px; padding: 10px 20px;">
                    <span>Tax on Shipping (net):</span>
                    <span>$<?php echo number_format($report_data['total_shipping_tax'], 2); ?></span>
                </div>
                <div class="summary-item" style="border-top: 2px solid #ddd; padding-top: 15px; margin-top: 15px;">
                    <span>Total Taxable Sales (net):</span>
                    <span style="color: #0073aa;"><strong>$<?php echo number_format($report_data['total_taxable_sales'], 2); ?></strong></span>
                </div>
                <div class="summary-item" style="border-top: 3px solid #0073aa; padding-top: 15px;">
                    <span>Total Sales Tax to Remit:</span>
                    <span style="color: #0073aa;"><strong>$<?php echo number_format($report_data['total_sales_tax'], 2); ?></strong></span>
                </div>
            </div>

            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin: 20px 0;">
                <p style="margin: 0;"><strong>✓ Texas Sales Tax Includes:</strong> Product tax ($<?php echo number_format($report_data['total_cart_tax'], 2); ?>) + Shipping tax ($<?php echo number_format($report_data['total_shipping_tax'], 2); ?>) = <strong>$<?php echo number_format($report_data['total_sales_tax'], 2); ?></strong><?php if ($report_data['total_refunds'] > 0): ?><br><em>Note: All figures are net of refunds.</em><?php endif; ?></p>
            </div>
            <?php
        }

        // Show order details only for detailed format
        if ($report_format === 'detailed' && $report_data['total_orders'] > 0):
        ?>
        <h3>Order Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Order Date</th>
                    <th>City</th>
                    <th>ZIP</th>
                    <th>Taxable (net)</th>
                    <th>Tax (net)</th>
                    <th>Total (net)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['orders'] as $order):
                    $net_total = floatval($order->net_total);
                    $net_tax = floatval($order->net_tax);
                    $taxable_total = $net_total - $net_tax;
                    $row_style = $order->has_refund ? 'background-color: #fff8e5;' : '';
                ?>
                <tr style="<?php echo $row_style; ?>">
                    <td>
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $order->order_id . '&action=edit')); ?>" target="_blank">#<?php echo esc_html($order->order_id); ?></a>
                        <?php if ($order->has_refund): ?><span style="color: #d63638; font-size: 11px;"> (partial refund)</span><?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date('M j, Y', strtotime($order->order_date))); ?></td>
                    <td><?php echo esc_html($order->city); ?></td>
                    <td><?php echo esc_html($order->zip_code); ?></td>
                    <td>$<?php echo number_format($taxable_total, 2); ?></td>
                    <td>$<?php echo number_format($net_tax, 2); ?></td>
                    <td><strong>$<?php echo number_format($net_total, 2); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top: 10px; font-style: italic; color: #666;">
            <strong>Note:</strong> "Taxable" = amount before tax, "Total" = final amount including tax. All values are net of any refunds.
        </p>
        <?php
        elseif ($report_data['total_orders'] == 0):
        ?>
        <p><em>No Texas orders found for this date range.</em></p>
        <?php
        endif;
        ?>

        <?php
        return ob_get_clean();
    }
    
    /**
     * Send report via email (HTML format)
     */
    private function send_report_email($report_data, $start_date, $end_date, $email_address, $report_format = 'summary') {
        $subject = 'Texas Sales Tax Report - ' . date('F j, Y', strtotime($start_date)) . ' to ' . date('F j, Y', strtotime($end_date));

        // Build HTML email
        $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; padding: 20px; color: #333;">';

        // Header
        $message .= '<h1 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Texas Sales Tax Report</h1>';
        $message .= '<p style="font-size: 16px;"><strong>Period:</strong> ' . date('F j, Y', strtotime($start_date)) . ' to ' . date('F j, Y', strtotime($end_date)) . '</p>';

        $has_refunds = isset($report_data['total_refunds']) && $report_data['total_refunds'] > 0;

        if ($report_format === 'summary') {
            // Summary format
            $message .= '<div style="background: #f0f7ff; border: 2px solid #0073aa; border-radius: 8px; padding: 20px; margin: 20px 0;">';
            $message .= '<h2 style="margin-top: 0; color: #0073aa;">Summary for Texas Comptroller</h2>';
            $message .= '<table style="width: 100%; border-collapse: collapse;">';
            $message .= '<tr><td style="padding: 10px 0; border-bottom: 1px solid #ddd;">Total Orders to Texas:</td><td style="padding: 10px 0; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold;">' . number_format($report_data['total_orders']) . '</td></tr>';
            if ($has_refunds) {
                $message .= '<tr><td style="padding: 10px 0; border-bottom: 1px solid #ddd; background: #ffe6e6;">Total Refunds:</td><td style="padding: 10px 0; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold; color: #d63638; background: #ffe6e6;">-$' . number_format($report_data['total_refunds'], 2) . '</td></tr>';
            }
            $message .= '<tr><td style="padding: 10px 0; border-bottom: 1px solid #ddd;">Total Taxable Sales (net):</td><td style="padding: 10px 0; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold; color: #0073aa;">$' . number_format($report_data['total_taxable_sales'], 2) . '</td></tr>';
            $message .= '<tr style="background: #e8f4fc;"><td style="padding: 15px 10px; font-size: 18px; font-weight: bold;">Total Sales Tax to Remit:</td><td style="padding: 15px 10px; text-align: right; font-size: 18px; font-weight: bold; color: #0073aa;">$' . number_format($report_data['total_sales_tax'], 2) . '</td></tr>';
            $message .= '</table></div>';
            $refund_note = $has_refunds ? ' All figures are net of refunds.' : '';
            $message .= '<p style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px;"><strong>Note:</strong> Sales tax includes tax on both products and shipping charges.' . $refund_note . ' Report the figures above to Texas Comptroller.</p>';
        } else {
            // Detailed format
            $message .= '<div style="background: #f0f7ff; border: 2px solid #0073aa; border-radius: 8px; padding: 20px; margin: 20px 0;">';
            $message .= '<h2 style="margin-top: 0; color: #0073aa;">Quarterly Summary</h2>';
            $message .= '<table style="width: 100%; border-collapse: collapse;">';
            $message .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #ddd;">Total Orders to Texas:</td><td style="padding: 8px 0; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold;">' . number_format($report_data['total_orders']) . '</td></tr>';
            $message .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #ddd;">Total Order Value (net):</td><td style="padding: 8px 0; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold;">$' . number_format($report_data['total_order_value'], 2) . '</td></tr>';
            if ($has_refunds) {
                $message .= '<tr><td style="padding: 8px 0; border-bottom: 1px solid #ddd; background: #ffe6e6;">Total Refunds:</td><td style="padding: 8px 0; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold; color: #d63638; background: #ffe6e6;">-$' . number_format($report_data['total_refunds'], 2) . '</td></tr>';
            }
            $message .= '</table>';

            $message .= '<h3 style="margin-top: 20px; margin-bottom: 10px;">Tax Breakdown (net of refunds)</h3>';
            $message .= '<table style="width: 100%; border-collapse: collapse; background: #f9f9f9; border-radius: 4px;">';
            $message .= '<tr><td style="padding: 8px 10px;">Product Tax:</td><td style="padding: 8px 10px; text-align: right;">$' . number_format($report_data['total_cart_tax'], 2) . '</td></tr>';
            $message .= '<tr><td style="padding: 8px 10px;">Shipping Tax:</td><td style="padding: 8px 10px; text-align: right;">$' . number_format($report_data['total_shipping_tax'], 2) . '</td></tr>';
            $message .= '</table>';

            $message .= '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
            $message .= '<tr style="border-top: 2px solid #ddd;"><td style="padding: 12px 0; font-weight: bold;">Total Taxable Sales (net):</td><td style="padding: 12px 0; text-align: right; font-weight: bold; color: #0073aa;">$' . number_format($report_data['total_taxable_sales'], 2) . '</td></tr>';
            $message .= '<tr style="background: #e8f4fc;"><td style="padding: 15px 10px; font-size: 18px; font-weight: bold;">Total Sales Tax to Remit:</td><td style="padding: 15px 10px; text-align: right; font-size: 18px; font-weight: bold; color: #0073aa;">$' . number_format($report_data['total_sales_tax'], 2) . '</td></tr>';
            $message .= '</table></div>';

            // Order details table
            if ($report_data['total_orders'] > 0) {
                $message .= '<h2 style="margin-top: 30px;">Order Details</h2>';
                $message .= '<table style="width: 100%; border-collapse: collapse; font-size: 14px;">';
                $message .= '<thead><tr style="background: #f5f5f5;">';
                $message .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Order</th>';
                $message .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Date</th>';
                $message .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">City</th>';
                $message .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">ZIP</th>';
                $message .= '<th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Taxable</th>';
                $message .= '<th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Tax</th>';
                $message .= '<th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Total</th>';
                $message .= '</tr></thead><tbody>';

                foreach ($report_data['orders'] as $order) {
                    $net_total = floatval($order->net_total);
                    $net_tax = floatval($order->net_tax);
                    $taxable_total = $net_total - $net_tax;
                    $row_style = $order->has_refund ? 'background: #fff8e5;' : '';
                    $refund_indicator = $order->has_refund ? ' <span style="color: #d63638; font-size: 11px;">(refund)</span>' : '';

                    $message .= '<tr style="' . $row_style . '">';
                    $message .= '<td style="padding: 8px 10px; border: 1px solid #ddd;">#' . esc_html($order->order_id) . $refund_indicator . '</td>';
                    $message .= '<td style="padding: 8px 10px; border: 1px solid #ddd;">' . date('M j, Y', strtotime($order->order_date)) . '</td>';
                    $message .= '<td style="padding: 8px 10px; border: 1px solid #ddd;">' . esc_html($order->city) . '</td>';
                    $message .= '<td style="padding: 8px 10px; border: 1px solid #ddd;">' . esc_html($order->zip_code) . '</td>';
                    $message .= '<td style="padding: 8px 10px; border: 1px solid #ddd; text-align: right;">$' . number_format($taxable_total, 2) . '</td>';
                    $message .= '<td style="padding: 8px 10px; border: 1px solid #ddd; text-align: right;">$' . number_format($net_tax, 2) . '</td>';
                    $message .= '<td style="padding: 8px 10px; border: 1px solid #ddd; text-align: right; font-weight: bold;">$' . number_format($net_total, 2) . '</td>';
                    $message .= '</tr>';
                }
                $message .= '</tbody></table>';
                $message .= '<p style="font-size: 13px; color: #666; font-style: italic;"><strong>Note:</strong> "Taxable" = amount before tax, "Total" = final amount including tax. All values are net of any refunds.</p>';
            }
        }

        // Footer
        $message .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">';
        $message .= '<p><strong>File your return at:</strong><br><a href="https://security.app.cpa.state.tx.us/public/login" style="color: #0073aa;">https://security.app.cpa.state.tx.us/public/login</a></p>';
        $message .= '<p style="font-size: 13px; color: #666;">Generated: ' . current_time('F j, Y g:i A') . '<br>';
        $message .= 'Site: ' . esc_html(get_bloginfo('name')) . ' (' . esc_html(get_bloginfo('url')) . ')</p>';
        $message .= '</div></body></html>';

        // Set headers for HTML email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email
        return wp_mail($email_address, $subject, $message, $headers);
    }

    /**
     * Send scheduled quarterly report
     */
    public function send_scheduled_report() {
        // Check if scheduled reports are enabled
        if (get_option('tx_tax_enable_scheduled') !== 'yes') {
            return;
        }

        $email = get_option('tx_tax_scheduled_email', get_option('admin_email'));
        $report_format = get_option('tx_tax_report_format', 'summary');

        // Get current quarter dates (scheduled reports run at END of quarter)
        $quarter_dates = $this->get_current_quarter_dates();

        // Generate report
        $report_data = $this->generate_report($quarter_dates['start'], $quarter_dates['end']);

        // Send email
        $this->send_report_email(
            $report_data,
            $quarter_dates['start'],
            $quarter_dates['end'],
            $email,
            $report_format
        );
    }

    /**
     * Get previous quarter dates
     */
    private function get_previous_quarter_dates() {
        $month = date('n');
        $year = date('Y');

        if ($month <= 3) {
            // Q1 - get Q4 of previous year
            $start = ($year - 1) . '-10-01';
            $end = ($year - 1) . '-12-31';
        } elseif ($month <= 6) {
            // Q2 - get Q1
            $start = $year . '-01-01';
            $end = $year . '-03-31';
        } elseif ($month <= 9) {
            // Q3 - get Q2
            $start = $year . '-04-01';
            $end = $year . '-06-30';
        } else {
            // Q4 - get Q3
            $start = $year . '-07-01';
            $end = $year . '-09-30';
        }

        return array('start' => $start, 'end' => $end);
    }

    /**
     * Schedule quarterly cron events
     */
    public static function schedule_quarterly_events() {
        // Clear any existing schedules first
        self::unschedule_quarterly_events();

        $current_year = date('Y');

        // Schedule reports for each quarter end
        $quarter_ends = array(
            $current_year . '-03-31 23:59:00',  // Q1
            $current_year . '-06-30 23:59:00',  // Q2
            $current_year . '-09-30 23:59:00',  // Q3
            $current_year . '-12-31 23:59:00',  // Q4
        );

        foreach ($quarter_ends as $date) {
            $timestamp = strtotime($date);
            // Only schedule if date is in the future
            if ($timestamp > current_time('timestamp')) {
                if (!wp_next_scheduled('tx_tax_quarterly_report', array($date))) {
                    wp_schedule_single_event($timestamp, 'tx_tax_quarterly_report', array($date));
                }
            }
        }
    }

    /**
     * Unschedule all quarterly events
     */
    public static function unschedule_quarterly_events() {
        $timestamp = wp_next_scheduled('tx_tax_quarterly_report');
        while ($timestamp) {
            wp_unschedule_event($timestamp, 'tx_tax_quarterly_report');
            $timestamp = wp_next_scheduled('tx_tax_quarterly_report');
        }
    }

    /**
     * Get current quarter dates
     */
    private function get_current_quarter_dates() {
        $month = date('n');
        $year = date('Y');
        
        if ($month <= 3) {
            $start = $year . '-01-01';
            $end = $year . '-03-31';
        } elseif ($month <= 6) {
            $start = $year . '-04-01';
            $end = $year . '-06-30';
        } elseif ($month <= 9) {
            $start = $year . '-07-01';
            $end = $year . '-09-30';
        } else {
            $start = $year . '-10-01';
            $end = $year . '-12-31';
        }
        
        return array('start' => $start, 'end' => $end);
    }
}

/**
 * Add TX Tax settings tab to WooCommerce
 */
function texas_sales_tax_add_settings_tab($settings) {
    // Define the settings class here when WooCommerce is loaded
    if (!class_exists('WC_Settings_TX_Tax') && class_exists('WC_Settings_Page')) {
        class WC_Settings_TX_Tax extends WC_Settings_Page {

            /**
             * Constructor
             */
            public function __construct() {
                $this->id    = 'tx_tax';
                $this->label = __('TX Sales Tax', 'texas-sales-tax-reporter');

                parent::__construct();

                // Hook into WooCommerce save process
                add_action('woocommerce_update_options_' . $this->id, array($this, 'save_and_reschedule'));
            }

            /**
             * Get settings array
             */
            public function get_settings() {
                $next_scheduled = wp_next_scheduled('tx_tax_quarterly_report');

                $settings = array(
                    array(
                        'title' => __('Texas Sales Tax Settings', 'texas-sales-tax-reporter'),
                        'type'  => 'title',
                        'desc'  => __('Configure automated quarterly sales tax reports for Texas.', 'texas-sales-tax-reporter'),
                        'id'    => 'tx_tax_settings'
                    ),

                    array(
                        'title'   => __('Enable Scheduled Reports', 'texas-sales-tax-reporter'),
                        'desc'    => __('Automatically generate and email reports at the end of each quarter (March 31, June 30, September 30, December 31)', 'texas-sales-tax-reporter'),
                        'id'      => 'tx_tax_enable_scheduled',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'title'       => __('Report Email Address', 'texas-sales-tax-reporter'),
                        'desc'        => __('Scheduled reports will be sent to this email address', 'texas-sales-tax-reporter'),
                        'id'          => 'tx_tax_scheduled_email',
                        'default'     => get_option('admin_email'),
                        'type'        => 'email',
                        'css'         => 'min-width: 300px;',
                        'placeholder' => get_option('admin_email'),
                    ),

                    array(
                        'title'   => __('Report Format', 'texas-sales-tax-reporter'),
                        'desc'    => __('Summary format provides just the totals needed for filing. Detailed format includes all order breakdowns.', 'texas-sales-tax-reporter'),
                        'id'      => 'tx_tax_report_format',
                        'default' => 'summary',
                        'type'    => 'select',
                        'options' => array(
                            'summary'  => __('Summary Only (Total Taxable Sales & Tax)', 'texas-sales-tax-reporter'),
                            'detailed' => __('Detailed (All Order Breakdowns)', 'texas-sales-tax-reporter'),
                        ),
                        'css'     => 'min-width: 300px;',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'tx_tax_settings'
                    ),
                );

                // Add information section about next scheduled report
                if ($next_scheduled) {
                    $settings[] = array(
                        'title' => __('Schedule Information', 'texas-sales-tax-reporter'),
                        'type'  => 'title',
                        'desc'  => sprintf(
                            __('<strong>Next Scheduled Report:</strong> %s', 'texas-sales-tax-reporter'),
                            date('F j, Y g:i A', $next_scheduled)
                        ),
                        'id'    => 'tx_tax_schedule_info'
                    );

                    $settings[] = array(
                        'type' => 'sectionend',
                        'id'   => 'tx_tax_schedule_info'
                    );
                }

                // Add quarter end dates information
                $settings[] = array(
                    'title' => __('Quarter End Dates', 'texas-sales-tax-reporter'),
                    'type'  => 'title',
                    'desc'  => __('Reports are automatically generated on:', 'texas-sales-tax-reporter') . '<br>' .
                               '<ul style="list-style: disc; margin-left: 20px;">' .
                               '<li><strong>Q1:</strong> March 31 at 11:59 PM</li>' .
                               '<li><strong>Q2:</strong> June 30 at 11:59 PM</li>' .
                               '<li><strong>Q3:</strong> September 30 at 11:59 PM</li>' .
                               '<li><strong>Q4:</strong> December 31 at 11:59 PM</li>' .
                               '</ul>',
                    'id'    => 'tx_tax_quarter_info'
                );

                $settings[] = array(
                    'type' => 'sectionend',
                    'id'   => 'tx_tax_quarter_info'
                );

                return apply_filters('woocommerce_get_settings_' . $this->id, $settings);
            }

            /**
             * Save settings and reschedule cron jobs
             */
            public function save_and_reschedule() {
                // Save settings using WooCommerce's built-in method
                $settings = $this->get_settings();
                WC_Admin_Settings::save_fields($settings);

                // Reschedule cron jobs based on new settings
                $enable_scheduled = get_option('tx_tax_enable_scheduled');

                if ($enable_scheduled === 'yes') {
                    Texas_Sales_Tax_Reporter::schedule_quarterly_events();
                } else {
                    Texas_Sales_Tax_Reporter::unschedule_quarterly_events();
                }
            }
        }
    }

    if (class_exists('WC_Settings_TX_Tax')) {
        $settings[] = new WC_Settings_TX_Tax();
    }
    return $settings;
}
add_filter('woocommerce_get_settings_pages', 'texas_sales_tax_add_settings_tab');

/**
 * Migrate checkbox value from true/false to yes/no
 */
function texas_sales_tax_migrate_checkbox_value() {
    $current_value = get_option('tx_tax_enable_scheduled');

    // Only migrate if value is boolean
    if ($current_value === true || $current_value === '1' || $current_value === 1) {
        update_option('tx_tax_enable_scheduled', 'yes');
    } elseif ($current_value === false || $current_value === '0' || $current_value === 0 || $current_value === '') {
        update_option('tx_tax_enable_scheduled', 'no');
    }
    // If already 'yes' or 'no', no migration needed
}
add_action('plugins_loaded', 'texas_sales_tax_migrate_checkbox_value', 5);

// Initialize plugin
function texas_sales_tax_reporter_init() {
    if (class_exists('WooCommerce')) {
        new Texas_Sales_Tax_Reporter();
    }
}
add_action('plugins_loaded', 'texas_sales_tax_reporter_init');

// Activation hook
function texas_sales_tax_reporter_activate() {
    // Schedule events if enabled
    if (get_option('tx_tax_enable_scheduled') === 'yes') {
        Texas_Sales_Tax_Reporter::schedule_quarterly_events();
    }
}
register_activation_hook(__FILE__, 'texas_sales_tax_reporter_activate');

// Deactivation hook
function texas_sales_tax_reporter_deactivate() {
    // Unschedule all events
    Texas_Sales_Tax_Reporter::unschedule_quarterly_events();
}
register_deactivation_hook(__FILE__, 'texas_sales_tax_reporter_deactivate');

