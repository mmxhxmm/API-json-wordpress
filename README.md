WooCommerce GraphQL Product Importer
This PHP script connects to an external GraphQL API and imports variable products with their variations into a WooCommerce store. It includes functionality for sideloading images, assigning categories, and avoiding duplicate SKUs. The script uses the official WooCommerce REST API (via the automattic/woocommerce PHP SDK) and interacts with WordPress functions to manage product data effectively.

Features
Connects to a GraphQL API to fetch product and variation data

Creates variable products with attributes (Color and Size)

Automatically sideloads product and variation images

Dynamically assigns categories (creates if not found)

Avoids duplicates by checking SKU existence

Logs creation, skipping, and errors for easy debugging

Requirements
WordPress installation with WooCommerce activated

Composer installed with automattic/woocommerce as a dependency

Access to an external GraphQL product API with a valid authentication token

PHP 7.4+ recommended
