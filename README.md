# HWsync (v0.0.0.7)

High-performance WordPress plugin & synchronization engine for PC hardware components and real-time multi-vendor pricing across Indian computer retail stores.

## Features
- Multi-vendor scrapers and data adapters (MDComputers, Vedant Computers, PrimeABGB, EliteHubs, PCStudio, etc.)
- Strict Sale Price (Offer Price) extraction prioritization over strikethrough MRP prices
- Headless Client-side & cURL multi-page scraping engines with smart pagination
- Canonical component database schema (`wp_hwsync_components`, `wp_hwsync_vendor_prices`)
- Intelligent normalization, fuzzy matching, and automated post-sync component merging
- On-Demand Multi-Vendor Component Deduplication and Price Merging (`wp hwsync merge` and Admin UI)
- Dynamic detailed specifications scraper from store product pages
- Interactive multi-vendor price comparison table shortcode `[hwsync_price_table]`
- Full Database CSV Export, Restore, and Reset utilities
