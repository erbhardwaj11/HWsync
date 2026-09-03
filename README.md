# HWsync (v0.0.5.2)

High-performance WordPress plugin & synchronization engine for PC hardware components and real-time multi-vendor pricing across Indian computer retail stores.

## Features
- Multi-vendor scrapers and data adapters (MDComputers, Vedant Computers, PrimeABGB, EliteHubs, PCStudio, The IT Depot, etc.)
- Strict Sale Price (Offer Price) extraction prioritization over strikethrough MRP prices
- Headless Client-side & cURL multi-page scraping engines with smart pagination
- Canonical component database schema (`wp_hwsync_components`, `wp_hwsync_vendor_prices`)
- Automated Post-Sync Component Matching & Price Consolidation
- Interactive Manual Component Merge Tool (Bulk & Custom Target/Source Consolidation)
- Interactive Store Listing Unmerge / Split Tool (Detach incorrectly paired store prices into clean standalone components)
- **Dedicated Product Image Synchronization Engine** (Scrapes vendor photos, renames to canonical component names, saves to uploads, registers in Media Library, and sets featured images)
- Dynamic detailed specifications scraper from store product pages
- Interactive multi-vendor price comparison table shortcode `[hwsync_price_table]`
- Full Database CSV Export, Restore, and Reset utilities
