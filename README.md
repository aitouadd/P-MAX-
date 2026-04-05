# P-MAX for Dolibarr

P-MAX is a Dolibarr custom module that enforces a **customer credit ceiling** before creating commercial documents.

It computes real exposure from unpaid invoices, undelivered order amounts, and optionally unpaid deliveries, then blocks new document creation when the customer reaches or exceeds the configured limit.

## Key Features

- Automatic control on these creation flows:
  - Proposals
  - Orders
  - Shipments
  - Invoices
- Exposure calculation based on:
  - Unpaid invoices (TTC)
  - Undelivered order amount (TTC)
  - Delivered-not-invoiced shipments (TTC, optional)
- Customer card budget panel:
  - Remaining credit
  - Usage percentage
  - Financial detail breakdown
- Blocking message with clear reason and remaining budget
- Per-customer P-MAX editing page for fast operations
- Dedicated rights model for read, setup, and limit editing
- English and French translations included

## Module Information

- Name: `P-MAX`
- Version: `1.0.0`
- Family: `CRM`
- Minimum PHP: `7.4`
- Minimum Dolibarr: `17.0`
- Dependencies:
  - `modSociete`
  - `modCommande`
  - `modFacture`

## Repository Structure

```text
admin/setup.php                                Module settings page
class/pmax.class.php                           P-MAX business calculator
class/actions_pmax.class.php                   Hooks (customer card widgets and controls)
core/modules/modPMax.class.php                 Module descriptor and rights
core/triggers/interface_99_modPMax_PMaxTriggers.class.php   Blocking triggers
pmax_customers.php                             Bulk/customer limit management page
css/pmax.css                                   UI styling
js/pmax.js                                     Front-end behavior
langs/en_US/pmax.lang                          English translations
langs/fr_FR/pmax.lang                          French translations
```

## How It Works

1. Determine effective customer limit:
   - `societe.outstanding_limit` if defined and > 0
   - otherwise global default `PMAX_DEFAULT_LIMIT`
2. Compute current exposure total:
   - unpaid invoices + undelivered orders + unpaid deliveries (optional)
3. On guarded document creation events, check:
   - if current total is already at/over limit: block immediately
   - else project amount from current document and block if projected total exceeds limit
4. Show operational indicators on the customer card.

## Installation

1. Copy this module folder to Dolibarr custom path:
   - `custom/pmax`
2. In Dolibarr, go to:
   - `Home -> Setup -> Modules/Applications`
3. Enable module:
   - `P-MAX`
4. Grant required user permissions.

## Configuration

Open module setup page:

- `Home -> Setup -> Modules/Applications -> P-MAX -> Setup`

Available settings:

- `PMAX_DEFAULT_LIMIT`: fallback limit used when customer limit is empty
- `PMAX_INCLUDE_DELIVERYPAYMENT`: include unpaid deliveries in exposure (`1` or `0`)

## Permissions

The module provides 3 rights:

- `Read P-MAX indicators`
- `Configure P-MAX`
- `Edit customer P-MAX limit on third-party card`

## Operations Guide

### Set a specific customer limit

- Open customer card and set Outstanding Limit (used by P-MAX), or
- Use `pmax_customers.php` to mass-manage customer limits

### Understand the budget panel

- **Remaining**: available amount before blocking
- **Usage**: consumption ratio of current exposure vs limit
- **Details**: values used in the formula (orders/invoices/deliveries)

### Unblock a customer

- Register payments and/or invoice pending deliveries/orders
- Re-check exposure after accounting updates

## Compatibility Notes

- For best results, keep shipment/order/invoice modules enabled when using P-MAX controls.
- If delivery contribution is not desired, disable it in setup.

## License

This module is distributed under the GNU General Public License v3.0 (or later), as indicated in source headers.

## Author

Custom
