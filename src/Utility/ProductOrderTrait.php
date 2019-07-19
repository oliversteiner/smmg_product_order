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
   * @param $product_order_nid
   * @param null $member_nid
   * @param null $token
   * @return array
   * @throws Exception
   */
  public static function productOrderVariables(
    $product_order_nid,
    $member_nid = null,
    $token = null
  ): array
  {
    $config = self::getConfig();

    $amount_suffix = $config->get('suffix');

    $variables = [];
    $variables['module'] = self::getModuleName();

    $variables['address']['gender'] = '';
    $variables['address']['first_name'] = '';
    $variables['address']['last_name'] = '';
    $variables['address']['street_and_number'] = '';
    $variables['address']['zip_code'] = '';
    $variables['address']['city'] = '';
    $variables['address']['email'] = '';
    $variables['address']['phone'] = '';

    $variables['product_orders'] = [];

    $variables['total']['number'] = 0;
    $variables['total']['product'] = 0;

    $variables['newsletter'] = false;

    $variables['id'] = $product_order_nid;
    $variables['token'] = false;

    // Clean Input
    $member_nid = trim($member_nid);
    $member_nid = (int)$member_nid;

    // Clean Input
    $product_order_nid = trim($product_order_nid);
    $product_order_nid = (int)$product_order_nid;

    // Load Terms from Taxonomy
    $amount_list = Helper::getTermsByID('product_order_amount');
    $gender_list = Helper::getTermsByID('gender');

    // product_order Order
    // ==============================================
    $product_order = Node::load($product_order_nid);

    if ($product_order && $product_order->bundle() === 'product_order') {
      // check token
      $node_token = Helper::getFieldValue($product_order, 'smmg_token');

      if ($token != $node_token) {
        // throw new AccessDeniedHttpException();
      }

      // Address
      // ==============================================
      $variables['address']['gender'] = Helper::getFieldValue(
        $product_order,
        'gender',
        $gender_list
      );
      $variables['address']['first_name'] = Helper::getFieldValue(
        $product_order,
        'first_name'
      );
      $variables['address']['last_name'] = Helper::getFieldValue(
        $product_order,
        'last_name'
      );
      $variables['address']['street_and_number'] = Helper::getFieldValue(
        $product_order,
        'street_and_number'
      );
      $variables['address']['zip_code'] = Helper::getFieldValue(
        $product_order,
        'zip_code'
      );
      $variables['address']['city'] = Helper::getFieldValue(
        $product_order,
        'city'
      );
      $variables['address']['email'] = Helper::getFieldValue(
        $product_order,
        'email'
      );
      $variables['address']['phone'] = Helper::getFieldValue(
        $product_order,
        'phone'
      );

      // Token
      $variables['token'] = Helper::getFieldValue($product_order, 'smmg_token');

      $product_orders = [];

      // Get All product_order_unit Nids
      $product_order_arr = Helper::getFieldValue(
        $product_order,
        'order_item',
        null,
        true
      );

      // load product_order_unit Nodes
      if ($product_order_arr && count($product_order_arr) > 0) {
        $i = 0;

        foreach ($product_order_arr as $nid) {
          $product_order_unit = Node::load($nid);
          if (
            $product_order_unit &&
            $product_order_unit->bundle() == 'product_order_item'
          ) {
            $product_orders[$i]['number'] = Helper::getFieldValue(
              $product_order_unit,
              'number_of'
            );
            $product_orders[$i]['product'] = Helper::getFieldValue(
              $product_order_unit,
              'product',
              $amount_list
            );

            $i++;
          }
        }
      }

      $variables['product_orders'] = $product_orders;

      // product_order Total
      // ==============================================
      $product_order_total_number = 0;
      $product_order_total_amount = 0;

      foreach ($product_orders as $product_order) {
        // Total Number
        $product_order_total_number += $product_order['number'];

        // Total Amount
        $row_total = $product_order['number'] * $product_order['product'];
        $product_order_total_amount += $row_total;
      }

      $product_order_name_singular = t('product_order');
      $product_order_name_plural = t('product_orders');

      $number_suffix =
        $product_order_total_number > 1
          ? $product_order_name_plural
          : $product_order_name_singular;

      // Save Vars
      $variables['total']['number'] = $product_order_total_number;
      $variables['total']['product'] = $product_order_total_amount;

      $variables['number_suffix'] = $number_suffix;
      $variables['amount_suffix'] = $amount_suffix;

      // Title
      $variables['title'] =
        ' - ' .
        $variables['address']['first_name'] .
        ' ' .
        $variables['address']['last_name'];
    }

    return $variables;
  }

  /**
   * @param $nid
   * @param $token
   * @throws Exception
   */
  private static function sendNotificationMail($nid, $token): void
  {
    $data = self::productOrderVariables($nid, $token);

    self::sendProductOrderMail($data);
  }

  /**
   * @param $number
   * @param $order_item
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws Exception
   */
  public static function newProductOrderItem($order_item): array
  {
    $result = [];
    $products = self::getAllProductsByID();

    $item_nid = $order_item['id'];
    $item_name = $products[$item_nid]['name'];
    $item_number_of = $order_item['number_of'];
    $item_price = $products[$item_nid]['price'];
    $item_price_shipping = $products[$item_nid]['price_shipping'];

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
          'field_price_in_cent' => $item_price*100,
        ]);

      // Save
      try {
        $node->save();
        $order_item_nid = $node->id();
        $result[] = $order_item_nid;
      } catch (EntityStorageException $e) {
      }
    }

    if ($order_item['download_number_of'] && $order_item['download_number_of'] !== 0) {

      $item_number_of_download = $order_item['download_number_of'];
      $item_price_download = $products[$item_nid]['price_download'];
      $title_download = $item_number_of_download . ' × ' . $item_name . ' (Download) ';


      $node_download = Drupal::entityTypeManager()
        ->getStorage('node')
        ->create([
          'type' => 'product_order_item',
          'status' => 0, //(1 or 0): published or not
          'promote' => 0, //(1 or 0): promoted to front page
          'title' => $title_download,
          'field_number_of' => $item_number_of_download,
          'field_product' => $item_nid,
          'field_price_in_cent' => $item_price_download*100,
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
   * @param array $data
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws Exception
   */
  public static function newOrder(array $data): array
  {
    $config = self::getConfig();

    // debug
    dpm($data);

    $order_items = $data['item'];

    // save product_order units
    foreach ($order_items as $order_item) {
      try {
        $results = self::newProductOrderItem($order_item);
        foreach ($results as $nid) {
          $order_items[] = $nid;
        }
      } catch (InvalidPluginDefinitionException $e) {
      } catch (PluginNotFoundException $e) {
      }
    }

    // Origin
    $origin = 'product_order';
    $origin_tid = Helper::getOrigin($origin);

    // Token
    $token = $data['token'];

    // Fieldset address
    $gender = $data['gender'];
    $first_name = $data['first_name'];
    $last_name = $data['last_name'];
    $street_and_number = $data['street_and_number'];
    $zip_code = $data['zip_code'];
    $city = $data['city'];
    $email = $data['email'];
    $phone = $data['phone'];

    $output = [
      'status' => false,
      'mode' => 'save',
      'nid' => false,
      'message' => '',
    ];
    $title = 'Album Bestellung "Musik mit Herz"';

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

    // product_order
    $new_order->get('field_order_item')->setValue($order_items);

    // Save
    try {
      $new_order->save();
      $new_order_nid = $new_order->id();

      // if OK
      if ($new_order_nid) {
        $message = t('Order successfully saved');
        $output['message'] = $message;
        $output['status'] = true;
        $output['nid'] = $new_order_nid;

        self::sendNotificationMail($new_order_nid, $token);
      }
    } catch (EntityStorageException $e) {
    } catch (Exception $e) {
    }

    return $output;
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
   * @param $module
   * @param $data
   * @param $templates
   * @return bool
   */
  public static function sendProductOrderMail($data): bool
  {
    $module = self::getModuleName();
    $templates = self::getTemplates();

    // Email::sendNotificationMail($module, $data, $templates);

    return true;
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
   *
   *
   * -- name
   * -- available
   * -- category
   * -- cover
   * -- description
   * -- price
   * -- price_download
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
        $products[] = [
          'name' => $name,
          'id' => $nid,
          'number_of' => 0,
          'number_of_download' => 0,
          'available' => $available,
          'category' => $category,
          'cover' => $cover,
          'description' => $description,
          'price' => $price,
          'price_total' => $price,
          'price_download' => $price_download,
          'price_download_total' => $price_download,
          'price_shipping' => $price_shipping,
          'producer' => $producer,
          'artist' => $artist,
          'author' => $author,
          'copyright' => $copyright,
        ];
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

}
