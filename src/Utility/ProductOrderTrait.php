<?php

namespace Drupal\smmg_product_order\Utility;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Link;
use Drupal\node\Entity\Node;
use Drupal\small_messages\Utility\Email;
use Drupal\small_messages\Utility\Helper;
use Exception;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait ProductOrderTrait
{
  /**
   * return product order with order items and total prices for TWIG
   *
   * @param $product_order_nid
   * @return array
   * @throws Exception
   */
  public static function productOrderVariables($product_order_nid): array
  {
    $products = self::getAllProductsByID();
    $config = self::getConfig();
    $title = $config->get('title');

    $variables = [];
    $variables['title'] = $title;
    $variables['module'] = self::getModuleName();

    $variables['address']['gender'] = '';
    $variables['address']['first_name'] = '';
    $variables['address']['last_name'] = '';
    $variables['address']['street_and_number'] = '';
    $variables['address']['zip_code'] = '';
    $variables['address']['city'] = '';
    $variables['address']['email'] = '';
    $variables['address']['phone'] = '';

    $variables['order_item'] = [];

    $variables['total']['number_of'] = 0;
    $variables['total']['product'] = 0;

    $variables['newsletter'] = false;

    $variables['id'] = $product_order_nid;
    $variables['token'] = false;

    // Clean Input
    $product_order_nid = trim($product_order_nid);
    $product_order_nid = (int)$product_order_nid;

    // Load Terms from Taxonomy
    $gender_list = Helper::getTermsByID('gender');

    // product_order Order
    // ==============================================
    $order_node = Node::load($product_order_nid);

    if ($order_node && $order_node->bundle() === 'product_order') {
      // check token

      // Address
      // ==============================================
      $variables['address']['gender'] = Helper::getFieldValue(
        $order_node,
        'gender',
        $gender_list
      );
      $variables['address']['first_name'] = Helper::getFieldValue(
        $order_node,
        'first_name'
      );
      $variables['address']['last_name'] = Helper::getFieldValue(
        $order_node,
        'last_name'
      );
      $variables['address']['street_and_number'] = Helper::getFieldValue(
        $order_node,
        'street_and_number'
      );
      $variables['address']['zip_code'] = Helper::getFieldValue(
        $order_node,
        'zip_code'
      );
      $variables['address']['city'] = Helper::getFieldValue(
        $order_node,
        'city'
      );
      $variables['address']['email'] = Helper::getFieldValue(
        $order_node,
        'email'
      );
      $variables['address']['phone'] = Helper::getFieldValue(
        $order_node,
        'phone'
      );

      // Token
      $variables['token'] = Helper::getFieldValue($order_node, 'smmg_token');

      $order_item = [];

      // Get All product_order_unit Nids
      $order_item_ids = Helper::getFieldValue(
        $order_node,
        'order_item',
        null,
        true
      );

      // load product_order_unit Nodes
      if ($order_item_ids && count($order_item_ids) > 0) {
        $i = 0;

        foreach ($order_item_ids as $nid) {
          $order_item_node = Node::load($nid);
          if (
            $order_item_node &&
            $order_item_node->bundle() === 'product_order_item'
          ) {
            $is_download = Helper::getFieldValue(
              $order_item_node,
              'is_download'
            );

            // get Number of CD
            $number_of = Helper::getFieldValue($order_item_node, 'number_of');

            // Product ID
            $product_id = Helper::getFieldValue($order_item_node, 'product');

            // Get Product
            $product = $products[$product_id];

            // build Twig Variables
            $order_item[$i]['id'] = $product_id;
            $order_item[$i]['number_of'] = $number_of;
            $order_item[$i]['price'] = $product['price'];
            $order_item[$i]['price_total'] = $number_of * $product['price'];

            if ($is_download) {
              $order_item[$i]['is_download'] = true;
              $order_item[$i]['name'] = $product['name'] . ' (Download)';
            } else {
              $order_item[$i]['is_download'] = false;
              $order_item[$i]['name'] = $product['name'];
            }

            $i++;
          }
        }
      }

      $variables['items'] = $order_item;

      // product_order Total
      // ==============================================
      $product_order_total_number = 0;

      // Total Number Of
      foreach ($order_item as $product_order_item) {
        $product_order_total_number += $product_order_item['number_of'];
      }
      $variables['order']['number_of'] = $product_order_total_number;

      // Discount Price
      $discount_price = Helper::getFieldValue($order_node, 'discount_price');
      $variables['order']['discount']['price'] = $discount_price;

      // Discount Number of
      $discount_number_of = Helper::getFieldValue(
        $order_node,
        'discount_number_of'
      );
      $variables['order']['discount']['number_of'] = $discount_number_of;

      // Shipping
      $shipping_total = Helper::getFieldValue(
        $order_node,
        'shipping_price_total'
      );
      $variables['order']['shipping']['price'] = $shipping_total;

      // Total
      $product_order_total = Helper::getFieldValue($order_node, 'price_total');
      $variables['order']['total']['price'] = $product_order_total;

      // Title
      $variables['order']['title'] =
        $title .
        ' - ' .
        $variables['address']['first_name'] .
        ' ' .
        $variables['address']['last_name'];
    }

    return $variables;
  }

  /**
   *
   * save new Order Item as node in DB
   * returns Array with new generated Node IDs
   *
   * @param $order_item
   * @param $products
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public static function newOrderItem($order_item, $products): array
  {
    $result = [];

    $item_nid = $order_item['id'];
    $item_name = $products[$item_nid]['name'];
    $item_price_shipping = $products[$item_nid]['price_shipping'];

    $item_number_of = $order_item['number_of'];
    $item_price = $products[$item_nid]['price'];

    $title = $item_number_of . ' × ' . $item_name;

    if ($item_number_of !== 0) {
      $node = Drupal::entityTypeManager()
        ->getStorage('node')
        ->create([
          'type' => 'product_order_item',
          'status' => 0, //(1 or 0): published or not
          'promote' => 0, //(1 or 0): promoted to front page
          'title' => $title,
          'field_number_of' => $item_number_of,
          'field_product' => $item_nid,
          'field_price_in_cent' => $item_price * 100,
          'field_shipping_price_in_cent' => $item_price_shipping * 100,
          'field_is_download' => 0,
        ]);

      // Save
      try {
        $node->save();
        $order_item_nid = $node->id();
        $result[] = $order_item_nid;
      } catch (EntityStorageException $e) {
      }
    }

    if (
      $order_item['number_of_download'] &&
      $order_item['number_of_download'] !== 0
    ) {
      $item_number_of_download = $order_item['number_of_download'];
      $item_price_download = $products[$item_nid]['price_download'];
      $title_download =
        $item_number_of_download . ' × ' . $item_name . '  (Download) ';

      $node_download = Drupal::entityTypeManager()
        ->getStorage('node')
        ->create([
          'type' => 'product_order_item',
          'status' => 0, //(1 or 0): published or not
          'promote' => 0, //(1 or 0): promoted to front page
          'title' => $title_download,
          'field_number_of' => $item_number_of_download,
          'field_product' => $item_nid,
          'field_price_in_cent' => $item_price_download * 100,
          'field_is_download' => 1,
        ]);

      // Save
      try {
        $node_download->save();
        $order_item_download_nid = $node_download->id();
        $result[] = $order_item_download_nid;
      } catch (EntityStorageException $e) {
      }
    }

    return $result;
  }

  /**
   *
   * saves new Order in DB
   *
   *
   * @param array $data
   * @return int
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws Exception
   */
  public static function newOrder(array $data): int
  {
    $result = 0;

    // Token
    $token = $data['token'];

    // Origin
    $origin = 'product_order';
    $origin_tid = Helper::getOrigin($origin);

    //  address
    $gender = $data['gender'];
    $first_name = $data['first_name'];
    $last_name = $data['last_name'];
    $street_and_number = $data['street_and_number'];
    $zip_code = $data['zip_code'];
    $city = $data['city'];
    $email = $data['email'];
    $phone = $data['phone'];

    // Title
    $config = Drupal::config('smmg_product_order.settings');
    $title = $config->get('title');
    $title .= ' - '.$last_name . ' ' . $first_name ;

    // New Node
    $storage = Drupal::entityTypeManager()->getStorage('node');
    $new_order = $storage->create([
      'type' => 'product_order',
      'title' => $title,
      'status' => 0, //(1 or 0): published or not
      'promote' => 0, //(1 or 0): promoted to front page
      'field_gender' => $gender,
      'field_first_name' => $first_name,
      'field_last_name' => $last_name,
      'field_phone' => $phone,
      'field_street_and_number' => $street_and_number,
      'field_zip_code' => $zip_code,
      'field_city' => $city,
      'field_email' => $email,

      // Origin
      'field_smmg_origin' => $origin_tid,

      // Token
      'field_smmg_token' => $token,
    ]);

    // Items

    // get all Products in array with id as key
    $products = self::getAllProductsByID();
    $order_items = $data['item'];

    // save product_order units
    foreach ($order_items as $order_item) {
      try {
        $results = self::newOrderItem($order_item, $products);
        foreach ($results as $nid) {
          $order_items[] = $nid;
        }
      } catch (InvalidPluginDefinitionException $e) {
      } catch (PluginNotFoundException $e) {
      }
    }

    $new_order->get('field_order_item')->setValue($order_items);

    // Discount
    $arr_discount = self::calculateDiscount($data, $products);
    $discount_in_cent = $arr_discount['price'];
    $discount_number_of = $arr_discount['number_of'];
    $discount = $discount_in_cent / 100;
    $new_order->get('field_discount_price')->setValue($discount);
    $new_order->get('field_discount_number_of')->setValue($discount_number_of);

    // Shipping
    $price_shipping_in_cent = self::calculateShipping($data, $products);
    $price_shipping = $price_shipping_in_cent / 100;
    $new_order->get('field_shipping_price_total')->setValue($price_shipping);

    // Total ink. Discount & Shipping
    $total_in_cent = self::getTotal($data, $products);
    $total =
      ($total_in_cent - $discount_in_cent + $price_shipping_in_cent) / 100;
    $new_order->get('field_price_total')->setValue($total);

    // Save
    try {
      $new_order->save();
      $new_order_nid = $new_order->id();
      $result = $new_order_nid;
      self::sendEmail($new_order_nid, $token);
    } catch (EntityStorageException $e) {
    } catch (Exception $e) {
    }
    return $result;
  }

  /**
   * @return array
   */
  public static function getTemplateNames(): array
  {
    return ['thank_you', 'email_html', 'email_plain'];
  }

  /**
   * @return array
   */
  public static function getTemplates(): array
  {
    $module = self::getModuleName();

    $template_names = self::getTemplateNames();
    return Helper::getTemplates($module, $template_names);
  }

  /**
   * @return Drupal\Core\Config\ImmutableConfig
   */
  public static function getConfig(): Drupal\Core\Config\ImmutableConfig
  {
    $module = self::getModuleName();
    return Drupal::config($module . '.settings');
  }

  public static function getAllProductNids()
  {
    $query =
      //
      // Condition
      \Drupal::entityQuery('node')
        //  ->condition('status', 1)
        ->condition('type', 'product');

    // Order by
    $query
      ->sort('created', 'DESC')
      ->sort('title')
      ->accessCheck(false);

    $nids = $query->execute();

    if (count($nids) === 0) {
      return false;
    }
    return $nids;
  }

  /**
   * @return array
   *
   * -- name
   * -- id
   * -- number_of
   * -- number_of_download
   * -- available
   * -- category
   * -- cover
   * -- description
   * -- price
   * -- price_total
   * -- price_download
   * -- price_total_download
   * -- price_shipping
   * -- producer
   * -- artist
   * -- author
   * -- copyright
   *
   * @throws Exception
   */
  public static function getAllProducts(): array
  {
    $products = [];

    $nids = self::getAllProductNids();

    if ($nids && is_array($nids)) {
      $entity_list = Node::loadMultiple($nids);

      foreach ($entity_list as $nid => $node) {
     $products[] = self::getProductVariables($node);
      }
    }

    return $products;
  }

  /**
   * @return array
   * @throws Exception
   */
  public static function getAllProductsByID(): array
  {
    $products_by_nid = [];
    $products = self::getAllProducts();

    foreach ($products as $product) {
      $products_by_nid[$product['id']] = $product;
    }

    return $products_by_nid;
  }

  /**
   * return total price product in cent
   * @param $data
   * @param $products
   * @return int
   */
  public static function getTotal($data, $products): int
  {
    $total = 0;
    foreach ($data['item'] as $item) {
      $id = $item['id'];
      $product = $products[$id];
      $price = $product['price'] * 100;
      $price_download = $product['price_download'] * 100;

      // CD
      $price_total = $item['number_of'] * $price;
      $total += $price_total;

      // Download
      $price_total_download = $item['number_of_download'] * $price_download;
      $total += $price_total_download;
    }

    return $total;
  }

  /**
   * return shipping price in cent
   *
   * @param $data
   * @param $products
   * @return int
   */
  public static function calculateShipping($data, $products): int
  {
    $shipping_queue = [0];

    foreach ($data['item'] as $item) {
      $id = $item['id'];
      $product = $products[$id];
      $price_shipping = $product['price_shipping'] * 100;
      $price_shipping_total = $price_shipping;
      $shipping_queue[] = $price_shipping_total;
    }
    return max($shipping_queue);
  }

  /**
   * return discount price in cent
   *
   * @param $data
   * @param $products
   * @return array
   */
  public static function calculateDiscount($data, $products): array
  {
    $discount_number_of_all = 0;
    $discount_price_total_all = 0;

    foreach ($data['item'] as $item) {
      $id = $item['id'];
      $product = $products[$id];
      $price_download = $product['price_download'] * 100;

      // Number of
      $number_of_cds = $item['number_of'];
      $number_of_downloads = $item['number_of_download'];

      if ($number_of_cds >= $number_of_downloads) {
        $discount_number_of = $number_of_downloads;
      } else {
        $discount_number_of = $number_of_cds;
      }

      $price_total_download = $discount_number_of * $price_download;

      $discount_number_of_all += $discount_number_of;
      $discount_price_total_all += $price_total_download;
    }

    $discount = [
      'number_of' => $discount_number_of_all,
      'price' => $discount_price_total_all,
    ];
    return $discount;
  }

  public static function getProductVariables($node): array
  {
    // name
    $id = $node->id();

    // name
    $name = $node->getTitle();

    // available
    $field_name = 'product_available';
    $available = Helper::getFieldValue($node, $field_name);

    // category
    $field_name = 'product_category';
    $term_list = 'product_category';
    $value = Helper::getFieldValue($node, $field_name, $term_list);
    $category = $value;

    // cover
    $field_name = 'product_cover';
    $value = Helper::getFieldValue($node, $field_name);
    $cover = $value;

    // description
    $field_name = 'product_description';
    $value = Helper::getFieldValue($node, $field_name);
    $description = $value;

    // price
    $field_name = 'product_price';
    $value = Helper::getFieldValue($node, $field_name);
    $price = $value;

    // price_download
    $field_name = 'product_price_download';
    $value = Helper::getFieldValue($node, $field_name);
    $price_download = $value;

    // price_shipping
    $field_name = 'product_price_shipping';
    $value = Helper::getFieldValue($node, $field_name);
    $price_shipping = $value;

    // producer
    $field_name = 'product_producer';
    $term_list = 'track_producer';
    $value = Helper::getFieldValue($node, $field_name, $term_list);
    $producer = $value;

    // $artist
    $field_name = 'track_artist';
    $term_list = 'track_artist';
    $value = Helper::getFieldValue($node, $field_name, $term_list);
    $artist = $value;

    // author
    $field_name = 'track_author';
    $term_list = 'track_author';
    $value = Helper::getFieldValue($node, $field_name, $term_list);
    $author = $value;

    // copyright
    $field_name = 'track_copyright';
    $term_list = 'track_copyright';
    $value = Helper::getFieldValue($node, $field_name, $term_list);
    $copyright = $value;

    // write returns
    return [
      'name' => $name,
      'id' => $id,
      'number_of' => 0,
      'number_of_download' => 0,
      'available' => $available,
      'category' => $category,
      'cover' => $cover,
      'description' => $description,
      'price' => $price,
      'price_total' => $price,
      'price_download' => $price_download,
      'price_total_download' => $price_download,
      'price_shipping' => $price_shipping,
      'producer' => $producer,
      'artist' => $artist,
      'author' => $author,
      'copyright' => $copyright,
    ];
  }
}
