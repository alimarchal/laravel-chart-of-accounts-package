---
title: FBR invoices
summary: Send sales invoices to FBR and keep the number they return.
---
## What it is

For businesses that must report sales tax invoices to Pakistan's FBR: posted sales invoices and credit notes are turned into the digital-invoice format, sent, and the invoice number FBR returns is stored.

## When to use it

After posting a sales invoice, if your business is registered for FBR digital invoicing.

## How to

1. Start in **test mode** (the default): invoices are accepted locally with a generated number, so you can see how it works. Nothing leaves your server.
2. Open the list, pick an invoice and **Send**. An accepted invoice is never sent twice; a refused one keeps the reason and can be retried.
3. When ready, switch the configuration to live mode with your token (see the README).

## Good to know

- The seller identity and defaults (HS code, unit, sale type) come from the configuration.
- Every attempt stores what was sent and received.

## Related

[[party-documents]] · [[tax]]
