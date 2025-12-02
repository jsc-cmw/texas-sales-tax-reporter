# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress/WooCommerce plugin that generates quarterly sales tax reports for Texas. The plugin calculates total sales tax including both product tax and shipping tax (as required by Texas law), and provides detailed reporting with email delivery capabilities.

**Plugin Name:** Texas Sales Tax Reporter
**Version:** 1.0.0
**Requirements:** WordPress 5.0+, PHP 7.2+, WooCommerce 3.0+

## Architecture

### Single-File Plugin Structure
- `texas-sales-tax-reporter.php` - Complete plugin implementation in a single file
- No external dependencies, build process, or package manager
- Uses WordPress admin_menu hooks to add a submenu under WooCommerce
- AJAX-based report generation with jQuery on the frontend

### Core Components

**Texas_Sales_Tax_Reporter Class** - Main plugin class with these responsibilities:
1. **Admin Interface** (`admin_page()` method) - Form-based UI with date pickers, email input, and quarterly presets
2. **Report Generation** (`generate_report()` method) - SQL query to fetch Texas orders from WooCommerce database
3. **Data Presentation** (`generate_report_html()` method) - Renders summary and detailed order tables
4. **Email Delivery** (`send_report_email()` method) - Sends plain-text formatted reports via wp_mail

### Database Query Logic

The plugin queries WooCommerce's order metadata using these key fields:
- `_order_total` - Final total paid by customer
- `_order_tax` - **Total tax to remit** (product + shipping tax)
- `_cart_tax` - Product tax only
- `_order_shipping` - Shipping cost charged
- `_order_shipping_tax` - Tax on shipping
- `_shipping_state` - Filters for 'TX' only
- Order statuses: `wc-completed` and `wc-processing` only

Query structure uses MAX(CASE) pattern to pivot multiple postmeta rows into columns for each order.

### Texas Tax Calculation Rules

**Critical:** In Texas, shipping charges are taxable when the shipped items are taxable. The plugin correctly reports:
- **Total Sales Tax to Remit** = Product Tax + Shipping Tax
- This is stored in WooCommerce's `_order_tax` field and is the primary value reported

See `TEXAS-SALES-TAX-REFERENCE.md` for complete Texas sales tax rules and filing guidance.

## Development

### Installation
1. Copy `texas-sales-tax-reporter.php` to `wp-content/plugins/texas-sales-tax-reporter/`
2. Activate via WordPress admin Plugins page
3. Access via WooCommerce → TX Sales Tax menu

### Testing
No automated test suite. Manual testing workflow:
1. Create test WooCommerce orders with Texas shipping addresses
2. Ensure orders have both product tax and shipping tax
3. Generate report for date range containing test orders
4. Verify calculations match individual order details in WooCommerce admin
5. Test email delivery functionality

### Code Modifications

**When modifying SQL queries:**
- Test with varying order statuses (completed, processing, refunded)
- Verify the date range logic (uses `BETWEEN` with adjusted end date to 23:59:59)
- Remember WooCommerce prefixes order statuses with `wc-` in the database

**When modifying tax calculations:**
- Always verify against actual WooCommerce order metadata
- Product tax is `_cart_tax`, shipping tax is `_order_shipping_tax`
- Total tax (`_order_tax`) should equal cart_tax + shipping_tax
- Do not modify tax calculation logic without consulting Texas sales tax law

**When modifying the admin interface:**
- Inline JavaScript uses jQuery (loaded via WordPress)
- Inline CSS styles the report output
- AJAX calls use WordPress ajaxurl global and nonce verification
- All user inputs are sanitized (sanitize_text_field, sanitize_email)

## Security

- Uses WordPress nonce verification for AJAX requests (`wp_verify_nonce`)
- Checks `manage_woocommerce` capability before generating reports
- Sanitizes all user inputs before database queries
- Uses prepared statements for SQL queries via `$wpdb->prepare()`
- Escapes all output in HTML views (esc_html, esc_url, esc_attr)

## File Reference

- `texas-sales-tax-reporter.php` - Complete plugin implementation
- `TEXAS-SALES-TAX-REFERENCE.md` - Texas sales tax rules, filing deadlines, and report interpretation guide
