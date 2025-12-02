# Texas Sales Tax Reference Guide

## What's Included in Texas Sales Tax

### ✅ **YES - Shipping IS Taxable in Texas**

According to Texas Tax Code, **shipping and handling charges are taxable** when:
1. The item being shipped is taxable
2. The shipping charge is part of the sale

Since most physical products sold online are taxable in Texas, the shipping costs for those items are also taxable.

### Tax Components in Your Report

Your plugin calculates **Total Sales Tax** which includes:

```
Total Sales Tax = Product Tax + Shipping Tax
```

**Example Order:**
- Product Subtotal: $100.00
- Texas Sales Tax (8.25%): $8.25 ← Product Tax
- Shipping Cost: $10.00
- Tax on Shipping (8.25%): $0.83 ← Shipping Tax
- **Total Tax to Remit: $9.08**
- Order Total: $119.08

## What the Plugin Reports

### Summary Section
- **Total Orders to Texas** - Count of all completed/processing orders shipped to TX
- **Total Order Value** - Grand total including products, shipping, and tax
- **Tax on Products** - Sales tax collected on merchandise only
- **Total Shipping Charged** - All shipping fees charged
- **Tax on Shipping** - Sales tax collected on shipping charges
- **Total Sales Tax to Remit** - The number you report to Texas Comptroller

### Per-Order Details
Each order shows:
- Order ID and Date
- City and ZIP code
- Order Total (everything)
- Shipping Cost
- Product Tax
- Shipping Tax
- **Total Tax** (what you collected from this customer)

## How WooCommerce Stores This Data

The plugin reads these WooCommerce order meta fields:

| Field | What It Contains |
|-------|------------------|
| `_order_total` | Final total paid by customer |
| `_order_tax` | **Total tax (product + shipping)** ← This is what you remit |
| `_cart_tax` | Tax on products only |
| `_order_shipping` | Shipping cost charged |
| `_order_shipping_tax` | Tax on shipping |

## Texas Sales Tax Rates

Texas has varying rates by location:
- **State Rate:** 6.25%
- **Local Rates:** Up to 2% additional
- **Combined Rate:** Typically 6.25% - 8.25%

Your WooCommerce store should be configured to charge the correct rate based on the customer's shipping address.

## Filing Your Return

**Texas Comptroller Filing Portal:**
https://security.app.cpa.state.tx.us/public/login

### What to Report
Use the **"Total Sales Tax to Remit"** amount from your quarterly report. This includes both product tax and shipping tax, which is correct for Texas.

### Reporting Frequency
- **Quarterly:** Most small businesses (less than $20,000/year in tax)
- **Monthly:** Larger businesses
- **Annually:** Very small businesses (by approval)

### Due Dates
| Quarter | Period | Due Date |
|---------|--------|----------|
| Q1 | Jan-Mar | April 20 |
| Q2 | Apr-Jun | July 20 |
| Q3 | Jul-Sep | October 20 |
| Q4 | Oct-Dec | January 20 |

## Verification Steps

To verify your numbers are correct:

1. **Run the plugin report** for your quarter
2. **Check a few sample orders** in WooCommerce:
   - Look at the order details
   - Verify the tax amount matches what's in the report
   - Confirm shipping was taxed if items are taxable
3. **Compare to your accounting** (if you use QuickBooks, etc.)
4. **File with confidence** using the "Total Sales Tax to Remit" number

## Important Notes

### ✅ What's Included:
- Sales tax on products
- Sales tax on shipping/handling
- All completed and processing orders
- Only orders shipped to Texas addresses

### ❌ What's NOT Included:
- Cancelled or refunded orders (not in completed/processing status)
- Orders shipped to other states
- Fees or discounts (tax is calculated on final amounts)

## Common Questions

**Q: Should I include shipping tax?**  
A: YES. In Texas, shipping is taxable when the items shipped are taxable.

**Q: What about discounts?**  
A: Tax is calculated on the final price after discounts. WooCommerce handles this automatically.

**Q: What about refunds?**  
A: The plugin only includes completed/processing orders. Refunded orders are not included.

**Q: What if I offer free shipping?**  
A: If shipping is $0, then shipping tax is $0. This works correctly.

**Q: Do I need to track county/city breakdowns?**  
A: No, for Texas sales tax filing, you report the total. The state handles distribution to local jurisdictions.

## Support

If you notice discrepancies:
1. Check individual orders in WooCommerce admin
2. Verify your tax settings are configured correctly
3. Ensure you're charging tax on shipping
4. Review order statuses (only completed/processing are included)

---

**Last Updated:** December 2024  
**Plugin Version:** 1.0.0
