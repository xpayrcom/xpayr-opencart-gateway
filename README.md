<div align="center">
  <a href="https://xpayr.com">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://xpayr.com/public/images/xpayr-logo-white.png" />
      <source media="(prefers-color-scheme: light)" srcset="https://xpayr.com/public/images/xpayr-logo-black.png" />
      <img src="https://xpayr.com/public/images/xpayr-logo-white.png" alt="XPayr Official Logo" width="220" />
    </picture>
  </a>
  <p>
    <a href="https://xpayr.com"><img src="https://xpayr.com/partner-badge.php?type=powered_by&theme=dark" alt="Powered by XPayr" height="28" /></a>
    <a href="https://mcp.xpayr.com"><img src="https://img.shields.io/badge/MCP_Portal-mcp.xpayr.com-029bfd?style=for-the-badge" alt="XPayr MCP Portal" height="28" /></a>
    <a href="https://docs.xpayr.com"><img src="https://img.shields.io/badge/Docs-docs.xpayr.com-10b981?style=for-the-badge" alt="XPayr Docs" height="28" /></a>
    <a href="https://www.npmjs.com/package/@xpayr/mcp-server"><img src="https://img.shields.io/npm/v/@xpayr/mcp-server?style=for-the-badge&color=cb3837&label=MCP%20NPM" alt="NPM @xpayr/mcp-server" height="28" /></a>
  </p>
  <p>
    <a href="https://xpayr.com"><b>Official Website</b></a> •
    <a href="https://mcp.xpayr.com"><b>AI &amp; MCP Portal (21 Tools)</b></a> •
    <a href="https://docs.xpayr.com"><b>Developer Docs</b></a> •
    <a href="https://xpayr.com/developers"><b>Developer Hub</b></a> •
    <a href="https://xpayr.com/tools/crypto-payment-fee-calculator"><b>0.5% Fee Calculator</b></a>
  </p>
</div>

---

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
