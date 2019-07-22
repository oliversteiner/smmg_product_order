<?php

namespace Drupal\smmg_product_order\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\smmg_product_order\Utility\ProductOrderTrait;
use Exception;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProductOrderController extends ControllerBase
{
  use ProductOrderTrait;

  /**
   * @return string
   */
  public static function getModuleName(): string
  {
    return 'smmg_product_order';
  }

  /**
   * @param $nid
   * @param null $token
   * @param string $output_mode
   * @return array|bool
   * @throws Exception
   */
  public function emailTemplateTest($nid, $token, $output_mode = 'html')
  {
    // get Data Variables
    $variables = self::checkTokenGetData($nid, $token);

    // Get Email Templates
    $templates = self::getTemplates();

    // Build HTML
    $build = false;

    // HTML Email
    if ($output_mode === 'html') {
      // Build HTML Content
      $template = file_get_contents($templates['email_html']);
      $build_html = [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $variables,
        ],
      ];
      $build = $build_html;
    }

    // Plaintext
    if ($output_mode === 'plain') {
      // Build Plain Text Content
      $template = file_get_contents($templates['email_plain']);
      $build_plain = [
        'description' => [
          '#type' => 'inline_template',
          '#template' => $template,
          '#context' => $variables,
        ],
      ];
      $build = $build_plain;
    }

    return $build;
  }

  /**
   * @param $nid
   * @param $token
   * @return array
   * @throws Exception
   */
  public function thankYouPage($nid, $token): array
  {

    // get Data Variables
    $variables = self::checkTokenGetData($nid, $token);

    // Get Email Templates
    $templates = self::getTemplates();

    // Build HTML
    $template = file_get_contents($templates['thank_you']);
    $build = [
      'description' => [
        '#type' => 'inline_template',
        '#template' => $template,
        '#attached' => [
          'library' => ['smmg_product_order/smmg_product_order.main'],
        ],
        '#context' => $variables,
      ],
    ];

    return $build;
  }

  /**
   * @param $nid
   * @param $token
   * @throws Exception
   */
  public static function sendEmail($nid, $token): void
  {

    // get Data Variables
    $variables = self::checkTokenGetData($nid, $token);


    try {
      $module = self::getModuleName();
      $templates = self::getTemplates();

      // Email::sendNotificationMail($module, $variables, $templates);
    } catch (Exception $e) {
    }
  }

  /**
   * @param $nid
   * @param $token
   * @return array | boolean
   * @throws Exception
   */
  public static function checkTokenGetData($nid, $token){
    // Make sure you don't trust the URL to be safe! Always check for exploits.
    if (!$nid || !is_numeric($nid)) {

      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }

    // get Data Variables
    $variables = self::productOrderVariables($nid);

    // Check genuine Token
    if (!$token || $token !== $variables['token']) {

      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }

    return $variables ?: false;
  }
}
