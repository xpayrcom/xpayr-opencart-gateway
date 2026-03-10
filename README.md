# XPayr Gateway for OpenCart (v3)

## Features
- Hosted crypto checkout via XPayr
- Dynamic `Network` and `Currency` lists from `GET /me/networks`
- Webhook callback with `X-XPayr-Signature` verification
- Auto webhook registration + secret sync on save
- Order status mapping for completed/failed/expired events

## Install
1. Zip contains `upload/` and `install.json`
2. OpenCart Admin -> Extensions -> Installer -> Upload `xpayr-opencart-gateway.zip`
3. Extensions -> Extensions -> Payments -> install/enable **XPayr Gateway**
4. Configure API settings and save

## Required config
- API Base URL: `https://xpayr.com/api/v1`
- Secret API Key: `sk_test_...` or `sk_live_...`
- Network and Currency from dropdowns

## Webhook
Callback URL used by module:
- `https://<store-domain>/index.php?route=extension/payment/xpayr/callback`

If auto-sync is enabled, module registers this URL and stores returned webhook secret automatically.

## Event mapping
- `payment.completed` -> Paid status
- `payment.failed` -> Failed status
- `payment.expired` -> Expired status
