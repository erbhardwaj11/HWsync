# HWsync (v0.0.0.2)

High-performance WordPress plugin & synchronization engine for PC hardware components and real-time multi-vendor pricing across Indian computer retail stores.

## Features
- Multi-vendor scrapers and data adapters (MDComputers, Vedant Computers, PrimeABGB, EliteHubs, PCStudio, etc.)
- Headless Client-side & cURL multi-page scraping engines with smart pagination
- Canonical component database schema (`wp_hwsync_components`, `wp_hwsync_vendor_prices`)
- Intelligent normalization and fuzzy component matching engine
- Direct Native PCSpecs Theme Database Sync (`wp_pc_components`, `wp_pc_vendor_prices`) powering the Headless React Part-Picker SPA & REST API (`/wp-json/pc-builder/v1/components`)
- Dynamic detailed specifications scraper from store product pages
- Interactive multi-vendor price comparison table shortcode `[hwsync_price_table]`
- Full Database CSV Export, Restore, and Reset utilities
