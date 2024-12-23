<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Payeer extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'payeer';
        $this->author = 'Payeer';
        $this->version = '1.0.2';
        $this->tab = 'payments_gateways';
        $this->need_instance = 1;
		$this->bootstrap = true;

        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
        $this->controllers = array('status', 'success', 'fail', 'payment');
        $this->is_eu_compatible = 1;
        $this->currencies = true;

        parent::__construct();

		$this->displayName = $this->l('Payeer');
		$this->description = $this->l('Payment via Payeer');

        $updateConfig = array('PS_OS_CHEQUE', 'PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_CANCELED', 'PS_OS_REFUND', 'PS_OS_ERROR', 'PS_OS_OUTOFSTOCK', 'PS_OS_BANKWIRE', 'PS_OS_PAYPAL', 'PS_OS_WS_PAYMENT');
        if (!Configuration::get('PS_OS_PAYMENT')) {
            foreach ($updateConfig as $u) {
                if (!Configuration::get($u) && defined('_'.$u.'_')) {
                    Configuration::updateValue($u, constant('_'.$u.'_'));
                }
            }
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions')) {
            return false;
        }
        return true;
    }
	
	public function getContent()
	{
		if (Tools::isSubmit('submitpayeer'))
		{
			$this->postProcess();
		}
		
		$this->html .= $this->renderForm();
		
		return $this->html;
	}
	
	private function postProcess()
	{
		Configuration::updateValue('merchant_url', Tools::getValue('merchant_url'));
		Configuration::updateValue('merchant_id', Tools::getValue('merchant_id'));
		Configuration::updateValue('secret_key', Tools::getValue('secret_key'));
		Configuration::updateValue('ip_filter', Tools::getValue('ip_filter'));
		Configuration::updateValue('payeer_log', Tools::getValue('payeer_log'));
		Configuration::updateValue('email_error', Tools::getValue('email_error'));
		
		$this->html .= $this->displayConfirmation($this->l('Settings updated.'));
	}

	public function renderForm()
	{
		$this->fields_form[0]['form'] = array(
				'legend' => array(
				'title' => $this->l('Settings'),
				'image' => _PS_ADMIN_IMG_.'information.png'
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Merchant URL'),
					'desc' => $this->l('URL for the payment'),
					'name' => 'merchant_url'
				),
				array(
					'type' => 'text',
					'label' => $this->l('ID store'),
					'desc' => $this->l('Store identifier registered in Payeer'),
					'name' => 'merchant_id'
				),
				array(
					'type' => 'text',
					'label' => $this->l('Secret key'),
					'desc' => $this->l('Secret key of merchant'),
					'name' => 'secret_key'
				),
				array(
					'type' => 'text',
					'label' => $this->l('IP - filter'),
					'desc' => $this->l('List of trusted IP addresses, you can specify the mask'),
					'name' => 'ip_filter'
				),
				array(
					'type' => 'text',
					'label' => $this->l('Path to the log file'),
					'desc' => $this->l('Path to the log file for payments via Payeer (for example, /payeer_orders.log)'),
					'name' => 'payeer_log'
				),
				array(
					'type' => 'text',
					'label' => $this->l('Email for errors'),
					'desc' => $this->l('Email to send payment errors'),
					'name' => 'email_error'
				)
			),
			'submit' => array(
				'name' => 'submitpayeer',
				'title' => $this->l('Save')
			)
		);
		$this->fields_form[1]['form'] = array(
			'legend' => array(
				'title' => $this->l('Merchant configuration information') ,
				'image' => _PS_ADMIN_IMG_.'information.png'
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Success URL'),
					'desc' => $this->l('URL to be used for query in case of successful payment.'),
					'name' => 'success_url',
					'size' => 120
				),
				array(
					'type' => 'text',
					'label' => $this->l('Fail URL'),
					'desc' => $this->l('URL to be used for query in case of failed payment.'),
					'name' => 'fail_url',
					'size' => 120
				),
				array(
					'type' => 'text',
					'label' => $this->l('Status URL'),
					'desc' => $this->l('Used for payment notification.'),
					'name' => 'status_url',
					'size' => 120
				)
			)
		);

		$helper = new HelperForm();
		$helper->module = $this;
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitpayeer';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.
			'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);
		return $helper->generateForm($this->fields_form);
	}
	
	public function getConfigFieldsValues()
	{
		$fields_values = array();

		$fields_values['merchant_url'] = Configuration::get('merchant_url') == '' ? 'https://payeer.com/merchant/' : Configuration::get('merchant_url');
		$fields_values['merchant_id'] = Configuration::get('merchant_id');
		$fields_values['secret_key'] = Configuration::get('secret_key');
		$fields_values['ip_filter'] = Configuration::get('ip_filter');
		$fields_values['payeer_log'] = Configuration::get('payeer_log');
		$fields_values['email_error'] = Configuration::get('email_error');

		$fields_values['status_url'] = $this->context->link->getModuleLink('payeer', 'status', array(), true);
		$fields_values['success_url'] = $this->context->link->getModuleLink('payeer', 'success', array(), true);
		$fields_values['fail_url'] = $this->context->link->getModuleLink('payeer', 'fail', array(), true);

		return $fields_values;
	}

    public function hasProductDownload($cart)
    {
        $products = $cart->getProducts();

        if (!empty($products)) {
            foreach ($products as $product) {
                $pd = ProductDownload::getIdFromIdProduct((int)($product['id_product']));
                if ($pd and Validate::isUnsignedInt($pd)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if ($this->hasProductDownload($params['cart'])) {
            return;
        }

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->trans('Payeer', array(), 'Modules.Payeer.Shop'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setAdditionalInformation($this->fetch('module:payeer/views/templates/hook/payeer_intro.tpl'));

        $payment_options = [
            $newOption,
        ];
        return $payment_options;
    }
}
