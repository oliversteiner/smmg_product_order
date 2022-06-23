<?php

namespace Drupal\smmg_product_order\Form;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\small_messages\Utility\Helper;
use Drupal\smmg_product_order\Controller\ProductOrderController;
use Drupal\smmg_product_order\Utility\ProductOrderTrait;
use Exception;

/**
 * Implements OrderForm form FormBase.
 *
 */
class ProductOrderForm extends FormBase
{
  public $number_options;
  public $suffix;
  public $product_order_singular;
  public $product_order_plural;
  public $text_number;
  public $text_product;
  public $text_add_product_orders;
  public $text_total;
  public $product_order_group_default;
  public $product_order_group_hide;
  public $options_product_order_group;
  public $products;

  use ProductOrderTrait;

  /**
   *  constructor.
   */
  public function __construct()
  {
    $this->number_options = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

    // Load Products
    $this->products = $this->getAllProducts();

    // Text
    $this->product_order_singular = t('product_order');
    $this->product_order_plural = t('product_orders');
    $this->text_total = t('Total');
    $this->text_number = t('Number');
    $this->text_product = t('Product');

    // from Config
    $config = Drupal::config('smmg_product_order.settings');
    $this->suffix = $config->get('suffix');

    // product_order Name from Settings
    $product_order_name_singular = $config->get('product_order_name_singular');
    $product_order_name_plural = $config->get('product_order_name_plural');

    if (!empty($product_order_name_singular)) {
      $this->product_order_singular = $product_order_name_singular;
    }
    if (!empty($product_order_name_plural)) {
      $this->product_order_plural = $product_order_name_plural;
    }
  }

  /**
   * {@inheritdoc}
   * @return string
   */
  public function getFormId(): string
  {
    return 'smmg_product_order_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $products = $this->products;
    $virtual_products = []; // Data for JS

    // Spam and Bot Protection
    honeypot_add_form_protection($form, $form_state, [
      'honeypot',
      'time_restriction',
    ]);

    // JS and CSS
    $form['#attached']['library'][] =
      'smmg_product_order/smmg_product_order.form';

    // Disable browser HTML5 validation
    $form['#attributes']['novalidate'] = 'novalidate';

    // Produkt Node
    // ==============================================

    $nid = $products[0]['id'];
    $node = node::load($nid);
    $view = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node,'teaser');
    #$view = Drupal::entityTypeManager()->getViewBuilder('node')->view('teaser') ;

    $form['product_order']['cd'] = [
      '#theme' => '',
      '#markup' => render($view),
    ];

    // Bestellliste
    // ==============================================
    $default_number = 0;

    $form['product_order']['item'] = [
      '#type' => 'fieldset',
      '#title' => 'Bestellliste',
      '#attributes' => ['class' => ['product-order-block']],
      '#prefix' => '<div class="ost-row-1">',

    ];

    // Table Header
    $form['product_order']['item']['header'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product-order-header" class="product-order-header">' .
        '</div>',
    ];

    $form['product_order']['item']['#tree'] = true; // This is to prevent flattening the form value

    // Table Body
    $i = 0;
    $row_nr = 0;

    foreach ($products as $product) {
      $default_number_of = 0;
      $id = $product['id'];

      // Product
      $name = $product['name'];
      $price = $product['price'];
      $price_shipping = $product['price_shipping'];
      $price_total = 0;

      // Product Download
      $name_download = $name . ' (Download)';
      $price_download = $product['price_download'];
      $price_download_shipping = 0;
      $price_download_total = 0;

      // Product (CD)
      // =====================================

      //  Row Start
      $form['product_order']['item'][$i]['start'] = [
        '#theme' => '',
        '#prefix' =>
          '<div id="product-order-row-' .
          $row_nr .
          '" class="product-order-row">',
      ];

      /*      $form['product_order']['item'][$i]['id-cd'] = [
        '#theme' => '',
        '#prefix' => '<span>' . $row_nr . '</span>',
      ];*/

      // Input Hidden Product id
      $form['product_order']['item'][$i]['id'] = [
        '#type' => 'hidden',
        '#value' => $id,
      ];

      // Input Number and Times
      $form['product_order']['item'][$i]['number_of'] = [
        '#type' => 'select',
        '#title' => '',
        '#options' => $this->number_options,
        '#default_value' => $default_number_of,
        '#required' => false,
        '#prefix' =>
          '<span class="product-order-row-number product-order-number">',
        '#suffix' =>
          '</span>' .
          '<span class="product-order-row-times product-order-times">&times;</span>',
      ];

      // Name / Product
      $form['product_order']['item'][$i]['name'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  class="product-order-row-product product-order-name">' .
          $name .
          '</span>',
      ];

      // Price
      $form['product_order']['item'][$i]['price'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  class="product-order-row-price product-order-price">' .
          $price .
          '</span>',
      ];

      // Price total
      $form['product_order']['item'][$i]['price_total'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  id="product-order-row-price-total-' .
          +$row_nr .
          '" class="product-order-row-price-total product-order-price">' .
          $price_total .
          '</span>',
      ];

      //  Row End
      $form['product_order']['item'][$i]['end'] = [
        '#theme' => '',
        '#suffix' => '</div>',
      ];

      // Product (CD)
      $virtual_products[$row_nr] = [
        'numberOf' => $default_number_of,
        'name' => $name,
        'id' => $id,
        'download' => false,
        'price' => $price * 100,
        'priceDownload' => $price_download * 100,
        'priceShipping' => $price_shipping * 100,
        'priceTotal' => $price_total * 100,
      ];

      // Product Item Download
      // ==========================================================

      $row_nr++;

      //  Download Row Start
      $form['product_order']['item'][$i]['download_start'] = [
        '#theme' => '',
        '#prefix' =>
          '<div id="product-order-row-' .
          $row_nr .
          '"
          class="product-order-row">',
      ];


      // ## Download Input Number and Times
      $form['product_order']['item'][$i]['number_of_download'] = [
        '#type' => 'select',
        '#title' => '',
        '#options' => $this->number_options,
        '#default_value' => $default_number_of,
        '#required' => false,
        '#prefix' =>
          '<span class="product-order-row-number product-order-number">',
        '#suffix' =>
          '</span>' .
          '<span class="product-order-row-times product-order-times">&times;</span>',
      ];

      // Download  Name / Product
      $form['product_order']['item'][$i]['download_name'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  class="product-order-row-product product-order-name">' .
          $name_download .
          '</span>',
      ];

      // Download Price
      $form['product_order']['item'][$i]['download_price'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  class="product-order-row-price product-order-price">' .
          $price_download .
          '</span>',
      ];

      // Download Price total
      $form['product_order']['item'][$i]['download_price_total'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  id="product-order-row-price-total-' .
          $row_nr .
          '" class="product-order-row-price-total product-order-price">' .
          $price_download_total .
          '</span>',
      ];

      //  Download Row End
      $form['product_order']['item'][$i]['download_end'] = [
        '#theme' => '',
        '#suffix' => '</div>',
      ];

      // Product (download)
      $virtual_products[$row_nr] = [
        'numberOf' => $default_number_of,
        'name' => $name_download,
        'id' => $id,
        'download' => true,
        'downloadFor' => $id,
        'price' => $price_download * 100,
        'priceShipping' => $price_download_shipping * 100,
        'priceTotal' => $price_download_total * 100,
      ];

      $i++;
      $row_nr++;
    } // End foreach Product

    // Virtual Product Items to  JS
    $form['#attached']['drupalSettings']['productOrder'][
    'products'
    ] = $virtual_products;

    // Discount
    $form['product_order']['item']['discount'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product-order-row-discount" class="product-order-row product-order-row-discount " style="display: none">' .
        '<span class="product-order-number"></span>' .
        '<span class="product-order-times"></span>' .
        '<span class="product-order-name"><span class="product-order-discount-number"></span></span>' .
        '<span class="product-order-price"></span>' .
        '<span class="product-order-total-discount-price product-order-price">0.00</span>' .
        '</div>',
    ];

    // Shipping
    $form['product_order']['item']['shipping'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product-order-row-shipping" class="product-order-row product-order-row-shipping product-order-row">' .
        '<span class="product-order-number"></span>' .
        '<span class="product-order-times"></span>' .
        '<span class="product-order-name">Verpackung und Versand </span>' .
        '<span class="product-order-price"></span>' .
        '<span class="product-order-total-shipping-price product-order-price">0.00</span>' .
        '</div>',
    ];

    // Table Total
    $form['product_order']['item']['total'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product-order-row-total" class="product-order-row product-order-row-total product-order-row">' .
        '<span class="product-order-number"></span>' .
        '<span class="product-order-times"></span>' .
        '<span class="product-order-total-label-total product-order-name">Total</span>' .
        '<span class="product-order-price"></span>' .
        '<span class="product-order-total-price-total product-order-price">0.00</span>' .
        '</div>',
    ];

    // Lieferung
    // ==============================================
    $form['product_order']['item']['lieferung'] = [
      '#theme' => '',
      '#markup' =>
        '<div class="product-order-info-lieferung product-order-info">' .
        'Alle Preise in CHF / inkl. MwSt.<br>' .
        'Voraussichtliche Lieferung: Ende Juli 2019.' .
        '</div>',
    ];

    // Details Infos
    // ==============================================

    $form['product_order']['details'] = [
      '#type' => 'fieldset',
      '#title' => 'Bestellinformationen',
      '#attributes' => ['class' => ['product-order-block']],
      '#suffix' => '</div>',

    ];

    $form['product_order']['details']['beschreibung'] = [
      '#theme' => '',
      '#markup' =>
        '<div class="product-order-info-text">' .
        '<h3>CD</h3>' .
        '<p>Die CD wird nach Abschluss der Produktion  mit Rechnung versandt. <br>' .
        'Rechnung zahlbar innert 10 Tagen.</p>' .
        '<h3>Download: mp3 </h3>' .
        '<p>Nach Zahlungseingang wird ihnen per Email  ein Link mit Zugriff auf die Dateien zugeschickt.<br>' .
        'Beim Kauf einer CD ist der mp3-Download inklusive.<br>' .
        '<span class="product-order-info">Dateiformat: mp3 / 256 bit / DRM-frei</span>' .
        '</p>' .
        '</div>',
    ];

    // Addresses
    // ==============================================

    // Fieldset Address
    $form['product_order']['postal_address'] = [
      '#type' => 'fieldset',
      '#title' => 'Liefer- und Rechnungsadresse',
      '#attributes' => ['class' => ['product-order-block']],
    ];

    // Gender / Firm

    // Load Taxonomy
    $vid = 'gender';
    $gender_options = Helper::getTermsByID($vid);
    $default_gender = array_key_first($gender_options);
    // Gender Input
    $form['product_order']['postal_address']['gender'] = [
      '#type' => 'select',
      '#title' => t('Gender'),
      '#default_value' => $default_gender,
      '#options' => $gender_options,
      '#required' => true,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // first_name
    $form['product_order']['postal_address']['first_name'] = [
      '#type' => 'textfield',
      '#title' => t('First Name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => true,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // last_name
    $form['product_order']['postal_address']['last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Last Name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => true,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // street_and_number
    $form['product_order']['postal_address']['street_and_number'] = [
      '#type' => 'textfield',
      '#title' => t('Street and Number'),
      '#size' => 60,
      '#maxlength' => 255,
      '#required' => true,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // zip_code
    $form['product_order']['postal_address']['zip_code'] = [
      '#type' => 'textfield',
      '#title' => t('ZIP'),
      '#size' => 5,
      '#maxlength' => 5,
      '#required' => true,
      '#prefix' => '<div class="form-group form-group-zip-city">',
    ];

    // city
    $form['product_order']['postal_address']['city'] = [
      '#type' => 'textfield',
      '#title' => t('City'),
      '#size' => 35,
      '#maxlength' => 255,
      '#required' => true,
      '#suffix' => '</div>',
    ];

    // email
    $form['product_order']['postal_address']['email'] = [
      '#type' => 'email',
      '#title' => 'Email (zwingend für den mp3-Download)',
      '#size' => 60,
      '#maxlength' => 255,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];

    // phone
    $form['product_order']['postal_address']['phone'] = [
      '#type' => 'textfield',
      '#title' => t('Phone'),
      '#size' => 60,
      '#maxlength' => 255,
      '#required' => false,
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];



    // Submit
    // ===============================================
    $form['product_order']['postal_address']['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['product_order']['postal_address']['save_data'] = [
      '#type' => 'submit',
      '#value' => 'Bestellung abschicken',
      '#allowed_tags' => ['style'],
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
    ];


    // hidden Token
    $token = random_bytes(20);
    $form['token'] = [
      '#type' => 'hidden',
      '#value' => bin2hex($token),
    ];
    //
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();

    // Fieldset address
    $gender = $values['gender'];
    $first_name = $values['first_name'];
    $last_name = $values['last_name'];
    $street_and_number = $values['street_and_number'];
    $zip_code = $values['zip_code'];
    $city = $values['city'];
    $email = $values['email'];

    // TODO check email if download is chosen

    // Address

    // Gender
    $t_gender = $this->t('Gender');
    if (!$gender || empty($gender)) {
      $form_state->setErrorByName(
        'gender',
        $this->t('Please fill in the field "@field"', ['@field' => $t_gender])
      );
    }

    // First Name
    $t_first_name = $this->t('First Name');
    if (!$first_name || empty($first_name)) {
      $form_state->setErrorByName(
        'first_name',
        $this->t('Please fill in the field "@field"', [
          '@field' => $t_first_name,
        ])
      );
    }

    // Last Name
    $t_last_name = $this->t('Last Name');
    if (!$last_name || empty($last_name)) {
      $form_state->setErrorByName(
        'last_name',
        $this->t('Please fill in the field "@field"', [
          '@field' => $t_last_name,
        ])
      );
    }

    // Street and Number
    $t_street_and_number = $this->t('Street and Number');
    if (!$street_and_number || empty($street_and_number)) {
      $form_state->setErrorByName(
        'street_and_number',
        $this->t('Please fill in the field "@field"', [
          '@field' => $t_street_and_number,
        ])
      );
    }

    // ZIP Code
    $t_zip_code = $this->t('ZIP');
    if (!$zip_code || empty($zip_code)) {
      $form_state->setErrorByName(
        'ZIP',
        $this->t('Please fill in the field "@field"', ['@field' => $t_zip_code])
      );
    }

    // City
    $t_city = $this->t('City');
    if (!$city || empty($city)) {
      $form_state->setErrorByName(
        'city',
        $this->t('Please fill in the field "@field"', ['@field' => $t_city])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $values = $form_state->getValues();

    // Send product_order Order
    try {
      $nid = ProductOrderController::newOrder($values);
      $token = $values['token'];
      $arg = ['nid' => $nid, 'token' => $token];

      // Go to Thank You Form
      $form_state->setRedirect('smmg_product_order.thank_you', $arg);
    } catch (InvalidPluginDefinitionException $e) {
    } catch (PluginNotFoundException $e) {
    } catch (Exception $e) {
    }
  }

  /**
   * @param $cents
   * @return string
   */
  public function convertCents($cents): string
  {
    return number_format($cents / 100, 2, '.', ' ');
  }
}

if (!function_exists('array_key_first')) {
  function array_key_first(array $arr)
  {
    foreach ($arr as $key => $unused) {
      return $key;
    }
    return null;
  }
}
