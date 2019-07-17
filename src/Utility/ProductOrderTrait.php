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

    if ($product_order && $product_order->bundle() == 'product_order') {
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
        'product_order_unit',
        null,
        true
      );

      // load product_order_unit Nodes
      if ($product_order_arr && count($product_order_arr) > 0) {
        $i = 0;

        foreach ($product_order_arr as $nid) {
          $product_order_unit = Node::load($nid);
          if ($product_order_unit && $product_order_unit->bundle() == 'product_order_unit') {
            $product_orders[$i]['number'] = Helper::getFieldValue(
              $product_order_unit,
              'product_order_number'
            );
            $product_orders[$i]['product'] = Helper::getFieldValue(
              $product_order_unit,
              'product_order_amount',
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
        $product_order_total_number > 1 ? $product_order_name_plural : $product_order_name_singular;

      // Save Vars
      $variables['total']['number'] = $product_order_total_number;
      $variables['total']['product'] = $product_order_total_amount;

      $variables['number_suffix'] = $number_suffix;
      $variables['amount_suffix'] = $amount_suffix;

      // Title
      $variables['title'] =
        $name_singular .
        ' - ' .
        $variables['address']['first_name'] .
        ' ' .
        $variables['address']['last_name'];
    }

    // Member & Newsletter
    // ==============================================
    if ($member_nid) {
      $member = Node::load($member_nid);

      if ($member && $member->bundle() == 'member') {
        // Newsletter
        $variables['newsletter'] = Helper::getFieldValue(
          $member,
          'smmg_accept_newsletter'
        );
      }
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

    self::sendproduct_orderMail($data);
  }

  /**
   * @param $number
   * @param $amount
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public static function newProductUnit($number, $amount): array
  {
    $config = self::getConfig();

    $output = [
      'status' => false,
      'mode' => 'save',
      'nid' => false,
      'message' => '',
    ];
    $suffix = $config->get('suffix');

    $amount_list = Helper::getTermsByID('product_order_amount');
    $title = $number . ' × ' . $amount_list[$amount] . ' ' . $suffix;
    $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->create([
        'type' => 'product_order_unit',
        'status' => 0, //(1 or 0): published or not
        'promote' => 0, //(1 or 0): promoted to front page
        'title' => $title,
        'field_product_order_number' => $number,
        'field_product_order_amount' => $amount,
      ]);

    // Save
    try {
      $node->save();
      $new_order_nid = $node->id();

      // if OK
      if ($new_order_nid) {
        $message = t('Information successfully saved');
        $output['message'] = $message;
        $output['status'] = true;
        $output['nid'] = $new_order_nid;
      }
    } catch (EntityStorageException $e) {
    }

    return $output;
  }

  /**
   * @param array $data
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public static function newOrder(array $data): array
  {
    $config = self::getConfig();

    $product_orders = [];

    // debug
    dpm($data);



    // save product_order units
    for ($i = 0; $i < 2; $i++) {
      $number = $data['product_orders']['product-' . $i]['number'];
      $amount = $data['product_orders']['product-' . $i]['product'];

      if ($number > 0) {
        try {
        //  $result = self::newProductUnit($number, $amount);
        //  $product_orders[$i] = $result['nid'];
        } catch (InvalidPluginDefinitionException $e) {
        } catch (PluginNotFoundException $e) {
        }
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
    $product_order_group = $data['product_order_group'];


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
    $new_order->get('field_product_unit')->setValue($product_orders);

    // Save
    try {
      $new_order->save();
      $new_order_nid = $new_order->id();

      // if OK
      if ($new_order_nid) {
        $message = t('product_order Order successfully saved');
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
  public static function sendproduct_orderMail($data): bool
  {
    $module = self::getModuleName();
    $templates = self::getTemplates();

    Email::sendNotificationMail($module, $data, $templates);

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
}
