<?php
// here are the requires
require_once('../wp-load.php');
require_once('../wp-content/themes/twentytwentyfour/functions.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once('../vendor/autoload.php');
// Enable detailed error reporting for development
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

// Set script execution time to 5 minutes
ini_set('max_execution_time', 300);

// Set memory limit (if necessary)
ini_set('memory_limit', '512M');




global $wpdb; // global variable

use Automattic\WooCommerce\Client;

// This is where we have to put client ID
$woocommerce = new Client(
    'your-web-url',
    'key_1',
    'key_2',
    [
        'version' => 'wc/v3',
    ]
);

// this part is about image
function sideload_image_and_get_id($image_url, $post_id)
{
    static $processed_images = array(); // Arreglo estático para mantener el registro a lo largo de la ejecución del script.

    // Verificar primero si ya tenemos un ID de imagen procesada para esta URL.
    if (isset($processed_images[$image_url])) {
        return $processed_images[$image_url];
    }

    $existing_attachment_id = attachment_url_to_postid($image_url);
    if ($existing_attachment_id) {
        // Si la imagen ya existe, agregarla al registro y devolver el ID existente.
        $processed_images[$image_url] = $existing_attachment_id;
        return $existing_attachment_id;
    }

    $desc = "Descargado para post " . $post_id;
    $file = media_sideload_image($image_url, $post_id, $desc, 'src');

    if (is_wp_error($file)) {
        error_log('Error al descargar la imagen: ' . $file->get_error_message());
        return null;
    }

    $args = array(
        'post_type' => 'attachment',
        'posts_per_page' => 1,
        'post_status' => 'inherit',
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => '_wp_attached_file',
                'value' => basename($file),
                'compare' => 'LIKE',
            ),
        ),
    );

    $attachments = get_posts($args);

    if ($attachments) {
        // Si la imagen es nueva, agregarla al registro y devolver el nuevo ID.
        $processed_images[$image_url] = $attachments[0]->ID;
        return $attachments[0]->ID;
    }

    return null;
}

// here I'm assigning products to the categories
function assignProductToCategories($product_id, $categories)
{
    foreach ($categories as $category) {
        $category_key = $category['key'];
        $slug = sanitize_title($category_key);
        $term = get_term_by('slug', $slug, 'product_cat');

        if (!$term) {
            $term_info = wp_insert_term($category_key, 'product_cat', ['description' => 'Categoría creada automáticamente', 'slug' => $slug]);
            if (!is_wp_error($term_info)) {
                wp_set_object_terms($product_id, [(int)$term_info['term_id']], 'product_cat', true);
            }
        } else {
            wp_set_object_terms($product_id, [$term->term_id], 'product_cat', true);
        }
    }
}

//  checking if the sku exists in woocommerce THIS ONE DOESN'T HAVE ANY PROBLEM
function sku_exists_in_woocommerce($sku)
{
    // echo "checkinjhg"; okay so it does enter here
    global $wpdb;
    $sku_found = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1;", $sku));
    return $sku_found ? true : false;
}

// here it is recollecting the colors
function recolectarColoresDeVariaciones($variaciones)
{
    $colores = [];
    foreach ($variaciones as $variacion) {
        // Añadir colores de 'itemColorName' si no está vacío
        if (!empty($variacion['itemColorName'])) {
            $colores[] = $variacion['itemColorName'];
        }

        // Añadir colores de 'filterColor' si está disponible
        if (isset($variacion['filterColor']) && is_array($variacion['filterColor'])) {
            foreach ($variacion['filterColor'] as $colorObjeto) {
                if (isset($colorObjeto['value'])) {
                    $colores[] = $colorObjeto['value'];
                }
            }
        }
    }
    return array_unique($colores); // Elimina duplicados
}
// here's the main game and this might be the place that has error
$ch = curl_init();
$url = "your-gateway-api-url";
$query = "
query {
    productSearch(
        q: \"\"
        assortmentId: \"your-assortment-ID\"  
        language: \"es\"
        page: 1
        pageSize: 100
    ) {
        count
        pageInfo {
            pageCount
            page
            pageSize
            hasNextPage
            hasPreviousPage
        }
        result {
            ...productFields
        }
    }
}

fragment productFields on Product {
    productNumber
    productName
    productCategory {
        key
    }
    productText
    variations {
        itemNumber
        itemColorName
        filterColor {
            value
        }
        sizes
        pictures {
            imageUrl
            thumbnailUrl
        }
        skus {
            sku
            availability
            skuSize {
                webtext
            }
        }
    }
}     
";

// here's the place where it does curl stuff
$data = json_encode(['query' => $query]);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    // strangely it hasn't expired yet
    'Authorization: ADD-YOUR BEARER HERE'
));

$response = curl_exec($ch);
curl_close($ch);

$respuesta = json_decode($response, true);

// this is main foreach but in this it does enter and it kind of works
foreach ($respuesta['data']['productSearch']['result'] as $product) {
    echo " Product ";
    $hasValidSizes = false;
    foreach ($product['variations'] as $variation) {
        if (!empty($variation['sizes'])) {
            $hasValidSizes = true;
            break;
        }
    }
    if (!$hasValidSizes) continue;

    // Check if the product already exists by SKU
    $product_id = wc_get_product_id_by_sku($product['productNumber']);
    
    // here we are checking the variations and stuff
    if (!$product_id) {
        $colorCounts = recolectarColoresDeVariaciones($product['variations']);

        $productData = [
            'name' => $product['productName'],
            'type' => 'variable',
            'description' => $product['productText'],
            'short_description' => $product['productText'],
            'sku' => $product['productNumber'],
            'attributes' => [
                [
                    'name' => 'Color',
                    'variation' => true,
                    'visible' => true,
                    'options' => array_values($colorCounts),
                ],
                [
                    'name' => 'Talla',
                    'variation' => true,
                    'visible' => true,
                    'options' => [],
                ],
            ],
        ];

        // doing the for each to get all the variations
        foreach ($product['variations'] as $variation) {
            foreach ($variation['skus'] as $skuDetail) {
                $productData['attributes'][1]['options'][] = $skuDetail['skuSize']['webtext'];
            }
        }

        $productData['attributes'][1]['options'] = array_unique($productData['attributes'][1]['options']);
        
        // Only create product if SKU doesn't already exist
        if (!sku_exists_in_woocommerce($product['productNumber'])) {
            try {
                $response = $woocommerce->post('products', $productData);
                $product_id = $response->id;
                echo "Producto creado: {$productData['sku']}\n";
            } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
                error_log("Error al crear producto SKU {$productData['sku']}: " . $e->getMessage());
                continue;
            } // it was thisss
            
            
            // Proceed with other operations like images and categories...
        } else {
            // SKU already exists, so you can update the existing product or skip
            echo "Producto con SKU {$product['productNumber']} ya existe, omitiendo la creación.\n";
            continue;  // Skip processing this product
        }
        // this one checks for the image
        if (!empty($product['variations'][0]['pictures'][0]['imageUrl'])) {
            $image_id = sideload_image_and_get_id($product['variations'][0]['pictures'][0]['imageUrl'], $product_id);
            if ($image_id) {
                set_post_thumbnail($product_id, $image_id);
            }
        }
        // and this one checks for the category
        if (isset($product['productCategory'])) {
            assignProductToCategories($product_id, $product['productCategory']);
        }
    }

    $processed_images = array();
    // dk I guess this is the part where we are supposed to upload variation shitttt
    foreach ($product['variations'] as $variation) {
        foreach ($variation['skus'] as $skuDetail) {
            // echo "I WANNA KNOW WHERE TF ERROR IS"; THIS WORKS 
            // Check if SKU already exists
            if (sku_exists_in_woocommerce($skuDetail['sku'])) continue; // Skip if SKU already exists

            $image_url = $variation['pictures'][0]['imageUrl'] ?? '';
            $image_id = null;

            if (!empty($image_url) && !isset($processed_images[$image_url])) {
                $image_id = sideload_image_and_get_id($image_url, $product_id);
                if ($image_id) {
                    $processed_images[$image_url] = $image_id;
                }
            } else {
                $image_id = $processed_images[$image_url] ?? null;
            }

            $color = isset($variation['itemColorName']) && !empty($variation['itemColorName']) ? $variation['itemColorName'] : '';
            if (empty($color) && isset($variation['filterColor']) && is_array($variation['filterColor'])) {
                foreach ($variation['filterColor'] as $colorObjeto) {
                    if (isset($colorObjeto['value'])) {
                        $color = $colorObjeto['value'];
                        break;
                    }
                }
            }

            $variationData = [
                'sku' => $skuDetail['sku'],
                'attributes' => [
                    [
                        'name' => 'Color',
                        'option' => $color,
                    ],
                    [
                        'name' => 'Talla',
                        'option' => $skuDetail['skuSize']['webtext'],
                    ],
                ],
            ];

            if ($image_id) {
                $variationData['image'] = ['id' => $image_id];
            }

            try {
                $woocommerce->post('products/' . $product_id . '/variations', $variationData);
            } catch (\Automattic\WooCommerce\HttpClient\HttpClientException $e) {
                error_log(" Error al crear variación SKU {$skuDetail['sku']}: " . $e->getMessage());
                continue;
            }
            
        }
    }
}

?>

<button onclick="history.back()">Volver Atrás</button>