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
    $this->number_options = [
      0 => 0,
      1 => 1,
      2 => 2,
      3 => 3,
      4 => 4,
      5 => 5,
      6 => 6,
      7 => 7,
      8 => 8,
      9 => 9,
      10 => 10];

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
    $values = $form_state->getUserInput();
    $products = $this->products;

    // Spam and Bot Protection
    honeypot_add_form_protection($form, $form_state, [
      'honeypot',
      'time_restriction',
    ]);

    // JS and CSS
    $form['#attached']['library'][] =
      'smmg_product_order/smmg_product_order.form';

    // Data to JS
    $form['#attached']['drupalSettings']['product_order']['numberOptions'] =
      $this->number_options;
    $form['#attached']['drupalSettings']['product_order']['products'] = $products;

    // Disable browser HTML5 validation
    $form['#attributes']['novalidate'] = 'novalidate';

    // Produkt Node
    // ==============================================
    $node = node::load(769);
    $view = node_view($node, 'teaser');

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
      '#attributes' => ['class' => ['product_order-block']],
    ];

    // Table Header
    $form['product_order']['item']['header'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product_order-table-header" class="product_order-table-header">' .
        '</div>',
    ];

    $form['product_order']['item']['#tree'] = TRUE; // This is to prevent flattening the form value


    // Table Body
    $i = 0;
    foreach ($products as $product) {
      $number = $i === 0 ? 1 : $default_number;

      //  Row Start
      $form['product_order']['item'][$i]['start'] = [
        '#theme' => '',
        '#prefix' =>
          '<div id="product_order-row-' .
          $i .
          '" class="product_order-table-row">',
      ];

      // Input Number and Times
      $form['product_order']['item'][$i]['number_of'] = [
        '#type' => 'select',
        '#title' => '',
        '#options' => $this->number_options,
        '#default_value' => $number,
        '#required' => false,
        '#prefix' =>
          '<span class="product_order-row-number product_order-number">',
        '#suffix' =>
          '</span>' .
          '<span class="product_order-row-times product_order-times">&times;</span>',
      ];

      // Input Hidden Product id
      $form['product_order']['item'][$i]['id'] = [
        '#type' => 'hidden',
        '#value' => $product['id'],
      ];

      // Name / Product
      $form['product_order']['item'][$i]['name'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  class="product_order-row-product product_order-name">' .
          $product['name'] .
          '</span>',
      ];

      // Price
      $form['product_order']['item'][$i]['price'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  class="product_order-row-price product_order-price">' .
          $product['price'] .
          '</span>',
      ];

      // Price total
      $form['product_order']['item'][$i]['price_total'] = [
        '#theme' => '',
        '#prefix' =>
          '<span  id="product_order-row-price-total-' .
          +$i .
          '" class="product_order-row-price-total product_order-price">' .
          $product['price_total'] .
          '</span>',
      ];

      //  Row End
      $form['product_order']['item'][$i]['end'] = [
        '#theme' => '',
        '#suffix' => '</div>',
      ];
      $i++;
    }
    // Discount
    $form['product_order']['item']['discount'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product_order-row-discount" class="product_order-row-discount product_order-table-row" style="display: none">' .
        '<span class="product_order-number"></span>' .
        '<span class="product_order-times"></span>' .
        '<span class="product_order-name"><span class="product_order-discount-number"></span></span>' .
        '<span class="product_order-price"></span>' .
        '<span class="product_order-total-discount-price product_order-price">0.00</span>' .
        '</div>',
    ];

    // Shipping
    $form['product_order']['item']['shipping'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product_order-row-shipping" class="product_order-row-shipping product_order-table-row">' .
        '<span class="product_order-number"></span>' .
        '<span class="product_order-times"></span>' .
        '<span class="product_order-name">Versand</span>' .
        '<span class="product_order-price"></span>' .
        '<span class="product_order-total-shipping-price product_order-price">5.00</span>' .
        '</div>',
    ];

    // Table Total
    $form['product_order']['item']['total'] = [
      '#theme' => '',
      '#prefix' =>
        '<div id="product_order-row-total" class="product_order-row-total product_order-table-row">' .
        '<span class="product_order-number"></span>' .
        '<span class="product_order-times"></span>' .
        '<span class="product_order-table-total-total product_order-name">Total</span>' .
        '<span class="product_order-price"></span>' .
        '<span class="product_order-table-total-price product_order-price">25.00</span>' .
        '</div>',
    ];

    // Lieferung
    // ==============================================
    $form['product_order']['item']['lieferung'] = [
      '#theme' => '',
      '#markup' =>
        '<div class="product_order-info-lieferung product_order-info">' .
        'Alle Preise in CHF / inkl. MwSt.<br>' .
        'Voraussichtliche Lieferung: Ende Juli 2019.' .
        '</div>',
    ];

    // Details Infos
    // ==============================================

    $form['product_order']['details'] = [
      '#type' => 'fieldset',
      '#title' => 'Bestellinformationen',
      '#attributes' => ['class' => ['product_order-block']],
    ];

    $form['product_order']['details']['beschreibung'] = [
      '#theme' => '',
      '#markup' =>
        '<div class="product_order-info-text">' .
        '<h3>CD</h3>' .
        '<p>Die CD wird nach Abschluss der Produktion  mit Rechnung versandt. <br>' .
        'Rechnung zahlbar innert 10 Tagen.</p>' .
        '<h3>Download: mp3 </h3>' .
        '<p>Nach Zahlungseingang wird ihnen per Email  ein Link mit Zugriff auf die Dateien zugeschickt.<br>' .
        'Beim Kauf einer CD ist der mp3-Download inklusive.<br>' .
        '<span class="product_order-info">Dateiformat: mp3 / 256 bit / DRM-frei</span>' .
        '</p>' .
        '</div>',
    ];

    // Addresses
    // ==============================================

    // Fieldset Address
    $form['product_order']['postal_address'] = [
      '#type' => 'fieldset',
      '#title' => 'Lieder- und Rechnungsadresse',
      '#attributes' => ['class' => ['product_order-block']],
    ];

    // Gender / Firm
    $gender_options = [0 => t('Please Chose')];

    // Load Taxonomy
    $vid = 'gender';
    $gender_options = Helper::getTermsByID($vid);

    // Gender Input
    $form['product_order']['postal_address']['gender'] = [
      '#type' => 'select',
      '#title' => t('Gender'),
      '#default_value' => $gender_options[0],
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

    // hidden Token
    $token = Crypt::randomBytes(20);
    $form['token'] = [
      '#type' => 'hidden',
      '#value' => bin2hex($token),
    ];

    // Submit
    // ===============================================
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['save_data'] = [
      '#type' => 'submit',
      '#value' => 'Bestellung abschicken',
      '#allowed_tags' => ['style'],
      '#prefix' => '<div class="form-group">',
      '#suffix' => '</div>',
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

    // generate Order Item Array

    // Token
    $token = $values['token'];
    $arg = ['token' => $token];

    // Send product_order Order
    try {
      $result = ProductOrderController::newOrder($values);

      if ($result) {
        if ($result['status']) {
          $arg['product_order_nid'] = (int)$result['nid'];
        } else {
          // Error on create new product_order Order
          if ($result['message']) {
            $this->messenger()->addMessage($result['message'], 'error');
          }
        }
      }

      // Go to  Thank You Form
      $form_state->setRedirect('smmg_product_order.product_order.thanks', $arg);
    } catch (InvalidPluginDefinitionException $e) {
    } catch (PluginNotFoundException $e) {
    }
  }

  /**
   * @param $cents
   * @return string
   */
  public function convertCents($cents)
  {
    return number_format($cents / 100, 2, '.', ' ');
  }


}
