<?php

namespace Drupal\smmg_product_order\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\smmg_product_order\Controller\ProductOrderController;

class ProductOrderSettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'smmg_product_order_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
            'smmg_product_order.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        // Load Settings
        $config = $this->config('smmg_product_order.settings');

        // Option Group
        $options_product_order_group  = ProductOrderController::getGroupOptions();

        // load all Template Names
        $template_list = ProductOrderController::getTemplateNames();

        // Options for Root Path
        $options_path_type = ['included'=> 'Included', 'module' => 'Module', 'theme' => 'Theme'];


        // Fieldset General
        //   - suffix
        //   - product_order Name Singular
        //   - product_order Name Plural
        //   - product_order Group Default
        //   - product_order Group Hide

        //  Fieldset Email
        //   - Email Address From
        //   - Email Address To
        //   - Email Test
        //
        //
        // Fieldset Twig Templates
        //   - Root of Templates
        //     - Module or Theme
        //     - Name of Module or Theme
        //   - Template Thank You
        //   - Template Email HTML
        //   - Template Email Plain
        //
        // Fieldset Fields for product_order
        //   - Number
        //   - Amount


        // Fieldset General
        // -------------------------------------------------------------
        $form['general'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('General'),
            '#attributes' => ['class' => ['product_order-settings-general']],
        ];

        // - suffix
        $form['general']['suffix'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('suffix (USD, EUR, SFR)'),
            '#default_value' => $config->get('suffix'),
        );

        //   - product_order Name Singular
        $form['general']['product_order_name_singular'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('product_order Name Singular'),
            '#default_value' => $config->get('product_order_name_singular'),
        );

        //   - product_order Name Plural
        $form['general']['product_order_name_plural'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('product_order Name Plural'),
            '#default_value' => $config->get('product_order_name_plural'),
        );

        //   - product_order Group Default
        $form['general']['product_order_group_default'] = array(
            '#type' => 'select',
            '#options' => $options_product_order_group,
            '#title' => $this->t('Default Group'),
            '#default_value' => $config->get('product_order_group_default'),
        );

        //   - product_order Name Plural
        $form['general']['product_order_group_hide'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Hide Group'),
            '#default_value' => $config->get('product_order_group_hide'),
        );

        // Fieldset Email
        // -------------------------------------------------------------
        $form['email'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Email Settings'),
            '#attributes' => ['class' => ['product_order-email-settings']],
        ];

        // - Email From
        $form['email']['email_from'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Email: From (newsletter@example.com)'),
            '#default_value' => $config->get('email_from'),
        );

        // - Email To
        $form['email']['email_to'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Email: to (sale@example.com, info@example.com)'),
            '#default_value' => $config->get('email_to'),
        );

        // - Email Test
        $form['email']['email_test'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Testmode: Don\'t send email to Subscriber'),
            '#default_value' => $config->get('email_test'),
        );

        // Fieldset Twig Templates
        // -------------------------------------------------------------

        $form['templates'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Templates'),
            '#attributes' => ['class' => ['product_order-settings-templates']],
        ];

        //   - Root of Templates
        $form['templates']['root_of_templates'] = array(
            '#markup' => $this->t('Path of Templates'),
        );
        //     - Module or Theme
        $form['templates']['get_path_type'] = array(
            '#type' => 'select',
            '#options' => $options_path_type,
            // '#value' => $default_number,
            '#title' => $this->t('Module or Theme'),
            '#default_value' => $config->get('get_path_type'),
        );

        //     - Name of Module or Theme
        $form['templates']['get_path_name'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Name of Module or Theme'),
            '#default_value' => $config->get('get_path_name'),
        );

        //   - Root of Templates
        $form['templates']['templates'] = array(
            '#markup' => $this->t('Templates'),
        );

        //  Twig Templates
        // -------------------------------------------------------------

        foreach ($template_list as $template) {

            $name = str_replace('_', ' ', $template);
            $name = ucwords(strtolower($name));
            $name = 'Template ' . $name;

            $form['templates']['template_' . $template] = array(
                '#type' => 'textfield',
                '#title' => $name,
                '#default_value' => $config->get('template_' . $template),
            );
        }

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state):void
    {

        $template_list = ProductOrderController::getTemplateNames();

        // Retrieve the configuration
      $config_name = $this->getEditableConfigNames();
        $this->configFactory->getEditable($config_name)

            // Fieldset Email
            // -------------------------------------------------------------
            // - Email From
            ->set('email_from', $form_state->getValue('email_from'))
            // - Email to
            ->set('email_to', $form_state->getValue('email_to'))
            // - Email Test
            ->set('email_test', $form_state->getValue('email_test'))
            //
            //
            // Fieldset Twig Templates
            // -------------------------------------------------------------
            // - Module or Theme
            ->set('get_path_type', $form_state->getValue('get_path_type'))
            // - Name of Module or Theme
            ->set('get_path_name', $form_state->getValue('get_path_name'))
            //
            ->save();

        //  Twig Templates
        // -------------------------------------------------------------
        $config = $this->configFactory->getEditable($config_name);

        foreach ($template_list as $template) {
            $template_name = 'template_' . $template;
            $config->set($template_name, $form_state->getValue($template_name));
        }

        $config->save();

        parent::submitForm($form, $form_state);
    }
}
