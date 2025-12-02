# Texas Sales Tax Reporter

A WordPress/WooCommerce plugin that generates quarterly sales tax reports for Texas, with automatic scheduling and email delivery.

## Features

- **Automatic Quarterly Reports**: Schedule reports to be generated and emailed automatically at the end of each quarter (March 31, June 30, September 30, December 31)
- **On-Demand Reports**: Generate reports for any custom date range
- **Multiple Report Formats**:
  - **Summary**: Clean format with just total taxable sales and tax (perfect for comptroller filing)
  - **Detailed**: Complete breakdown with all order details
- **Email Delivery**: Reports automatically emailed in plain text format
- **Texas Tax Compliance**: Correctly includes tax on both products and shipping charges as required by Texas law

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- WooCommerce 3.0 or higher

## Installation

1. Download the latest release
2. Upload the plugin file to WordPress via **Plugins → Add New → Upload Plugin**
3. Activate the plugin
4. Navigate to **WooCommerce → TX Sales Tax** to generate reports
5. Configure scheduled reports at **WooCommerce → Settings → TX Sales Tax**

## Usage

### Manual Report Generation

1. Go to **WooCommerce → TX Sales Tax**
2. Select start and end dates (or use quick quarter selection buttons)
3. Choose report format (Summary or Detailed)
4. Optionally enter an email address
5. Click **Generate Report**

### Scheduled Reports

1. Go to **WooCommerce → Settings → TX Sales Tax**
2. Check **Enable Scheduled Reports**
3. Enter the email address for reports
4. Select report format
5. Click **Save Settings**

Reports will automatically generate and email at the end of each quarter.

## Report Details

### Summary Format
- Total Orders to Texas
- Total Taxable Sales (amount before tax)
- Total Sales Tax to Remit

### Detailed Format
- Complete order-by-order breakdown
- Taxable amount, tax collected, and total for each order
- City and ZIP code information
- Product and shipping tax breakdown

## Texas Sales Tax Information

In Texas, sales tax applies to both products AND shipping charges for taxable items. This plugin correctly calculates:

- **Total Taxable Sales** = Order totals minus tax
- **Total Sales Tax** = Product tax + Shipping tax

See [TEXAS-SALES-TAX-REFERENCE.md](TEXAS-SALES-TAX-REFERENCE.md) for complete Texas sales tax rules and filing information.

## Development

For development guidance and architecture details, see [CLAUDE.md](CLAUDE.md).

## Version History

- **1.1.3** - Improved email formatting with cleaner tables and taxable amount column
- **1.1.0** - Added automatic quarterly scheduling feature
- **1.0.0** - Initial release

## Filing Your Return

Use the "Total Sales Tax to Remit" amount when filing with the Texas Comptroller at:
https://security.app.cpa.state.tx.us/public/login

## Support

For issues or questions, please visit the [GitHub repository](https://github.com/yourusername/texas-sales-tax-reporter).

## License

Copyright © Card Machine Works

## Author

Card Machine Works
https://cardmachineworks.com
