<h1><b>WooCommerce GraphQL Product Importer</b></h1>
<p>
This PHP script connects to an external GraphQL API and imports variable products with their variations into a WooCommerce store.
It includes functionality for sideloading images, assigning categories, and avoiding duplicate SKUs. The script uses the official WooCommerce REST API
(via the <code>automattic/woocommerce</code> PHP SDK) and interacts with WordPress functions to manage product data effectively.
</p>

<h2><b>Features</b></h2>
<ul>
    <li><b>Connects to a GraphQL API</b> to fetch product and variation data</li>
    <li><b>Creates variable products</b> with attributes (Color and Size)</li>
    <li><b>Automatically sideloads images</b> for both products and variations</li>
    <li><b>Dynamically assigns categories</b>, creating them if they do not exist</li>
    <li><b>Avoids duplicate entries</b> by checking for existing SKUs</li>
    <li><b>Logs actions and errors</b> for easier debugging and traceability</li>
</ul>

<h2><b>Requirements</b></h2>
<ul>
    <li><b>WordPress installation</b> with WooCommerce activated</li>
    <li><b>Composer installed</b> with the <code>automattic/woocommerce</code> package</li>
    <li><b>Access to a GraphQL product API</b> with a valid authentication token</li>
    <li><b>PHP 7.4+</b> recommended</li>
</ul>


