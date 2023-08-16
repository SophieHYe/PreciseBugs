<?php
/**
 * 2007-2020 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Foundation\Database\EntityManager;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Ps_Emailsubscription extends Module implements WidgetInterface
{
    /**
     * @var string Name of the module running on PS 1.6.x. Used for data migration.
     */
    const PS_16_EQUIVALENT_MODULE = 'blocknewsletter';

    const GUEST_NOT_REGISTERED = -1;
    const CUSTOMER_NOT_REGISTERED = 0;
    const GUEST_REGISTERED = 1;
    const CUSTOMER_REGISTERED = 2;

    const LEGAL_PRIVACY = 'LEGAL_PRIVACY';

    protected $_origin_newsletter;

    const TPL_COLUMN = 'ps_emailsubscription-column.tpl';
    const TPL_DEFAULT = 'ps_emailsubscription.tpl';

    /**
     * @param EntityManager $entity_manager
     */
    public function __construct(EntityManager $entity_manager)
    {
        $this->name = 'ps_emailsubscription';
        $this->need_instance = 0;

        $this->controllers = array('verification', 'subscription');

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Newsletter subscription', array(), 'Modules.Emailsubscription.Admin');
        $this->description = $this->trans('Keep in touch with your customers the way you want, add a form to the homepage of your store and allow all the curious to subscribe to your newsletter.', array(), 'Modules.Emailsubscription.Admin');
        $this->confirmUninstall = $this->trans('Are you sure that you want to delete all of your contacts?', array(), 'Modules.Emailsubscription.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);

        $this->entity_manager = $entity_manager;

        $this->version = '2.6.0';
        $this->author = 'PrestaShop';
        $this->error = false;
        $this->valid = false;
        $this->_files = array(
            'name' => array('newsletter_conf', 'newsletter_voucher'),
            'ext' => array(
                0 => 'html',
                1 => 'txt',
            ),
        );

        $this->_searched_email = null;

        $this->_html = '';
        if ($this->id) {
            $this->file = 'export_' . Configuration::get('PS_NEWSLETTER_RAND') . '.csv';
            $this->post_valid = array();

            // Getting data...
            $countries = Country::getCountries($this->context->language->id);

            // ...formatting array
            $countries_list = array($this->trans('All countries', array(), 'Admin.Global'));
            foreach ($countries as $country) {
                $countries_list[$country['id_country']] = $country['name'];
            }
        }
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook(
                array(
                    'actionFrontControllerSetMedia',
                    'displayFooterBefore',
                    'actionCustomerAccountAdd',
                    'actionObjectCustomerUpdateBefore',
                    'additionalCustomerFormFields',
                    'displayAdminCustomersForm',
                    'registerGDPRConsent',
                    'actionDeleteGDPRCustomer',
                    'actionExportGDPRData',
                    'actionCustomerAccountUpdate',
                )
            )
        ) {
            return false;
        }

        if ($this->uninstallPrestaShop16Module()) {
            // 1.6 Module exist and was uninstalled
            Db::getInstance()->execute('RENAME TABLE `' . _DB_PREFIX_ . 'newsletter` to `' . _DB_PREFIX_ . 'emailsubscription`');
        } else {
            Configuration::updateValue('PS_NEWSLETTER_RAND', mt_rand() . mt_rand());
            Configuration::updateValue('NW_SALT', Tools::passwdGen(16));
        }

        // New data
        $conditions = array();
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $conditions[(int) $lang['id_lang']] = $this->getConditionFixtures($lang);
        }
        Configuration::updateValue('NW_CONDITIONS', $conditions, true);

        return Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'emailsubscription` (
            `id` int(6) NOT NULL AUTO_INCREMENT,
            `id_shop` INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
            `id_shop_group` INTEGER UNSIGNED NOT NULL DEFAULT \'1\',
            `email` varchar(255) NOT NULL,
            `newsletter_date_add` DATETIME NULL,
            `ip_registration_newsletter` varchar(15) NOT NULL,
            `http_referer` VARCHAR(255) NULL,
            `active` TINYINT(1) NOT NULL DEFAULT \'0\',
            `id_lang` int(10) NOT NULL DEFAULT \'0\',
            PRIMARY KEY(`id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' default CHARSET=utf8');
    }

    public function uninstall()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'emailsubscription');

        return parent::uninstall();
    }

    /**
     * Migrate data from 1.6 equivalent module (if applicable), then uninstall
     */
    public function uninstallPrestaShop16Module()
    {
        if (!Module::isInstalled(self::PS_16_EQUIVALENT_MODULE)) {
            return false;
        }
        $oldModule = Module::getInstanceByName(self::PS_16_EQUIVALENT_MODULE);

        if ($oldModule) {
            // This closure calls the parent class to prevent data to be erased
            // It allows the new module to be configured without migration
            $parentUninstallClosure = function () {
                return parent::uninstall();
            };

            $parentUninstallClosure = $parentUninstallClosure->bindTo($oldModule, get_class($oldModule));
            $parentUninstallClosure();
        }

        return true;
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitUpdate')) {
            Configuration::updateValue('NW_CONFIRMATION_EMAIL', (bool) Tools::getValue('NW_CONFIRMATION_EMAIL'));
            Configuration::updateValue('NW_VERIFICATION_EMAIL', (bool) Tools::getValue('NW_VERIFICATION_EMAIL'));

            $conditions = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $lang) {
                if (Tools::getIsset('NW_CONDITIONS_' . $lang['id_lang'])) {
                    $conditions[$lang['id_lang']] = Tools::getValue('NW_CONDITIONS_' . $lang['id_lang']);
                }
            }

            Configuration::updateValue('NW_CONDITIONS', $conditions, true);
            $voucher = Tools::getValue('NW_VOUCHER_CODE');

            if ($voucher && !Validate::isDiscountName($voucher)) {
                $this->_html .= $this->displayError($this->trans('The voucher code is invalid.', array(), 'Admin.Notifications.Error'));
            } else {
                Configuration::updateValue('NW_VOUCHER_CODE', pSQL($voucher));
                $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
            }

        } elseif (Tools::isSubmit('subscribedmerged')) {
            $id = Tools::getValue('id');

            if (preg_match('/(^N)/', $id)) {
                $id = (int) Tools::substr($id, 1);
                $sql = 'UPDATE ' . _DB_PREFIX_ . 'emailsubscription SET active = 0 WHERE id = ' . $id;
                Db::getInstance()->execute($sql);
            } else {
                $c = new Customer((int) $id);
                $c->newsletter = (int) !$c->newsletter;
                $c->update();
            }

            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&conf=4&token=' . Tools::getAdminTokenLite('AdminModules'));

        } elseif (Tools::isSubmit('submitExport') && $action = Tools::getValue('action')) {
            $this->export_csv();
        } elseif (Tools::isSubmit('searchEmail')) {
            $this->_searched_email = Tools::getValue('searched_email');
        }

        $this->_html .= $this->renderForm();
        $this->_html .= $this->renderSearchForm();
        $this->_html .= $this->renderList();

        $this->_html .= $this->renderExportForm();

        return $this->_html;
    }

    public function renderList()
    {
        $fields_list = array(
            'id' => array(
                'title' => $this->trans('ID', array(), 'Admin.Global'),
                'search' => false,
            ),
            'shop_name' => array(
                'title' => $this->trans('Shop', array(), 'Admin.Global'),
                'search' => false,
            ),
            'gender' => array(
                'title' => $this->trans('Gender', array(), 'Admin.Global'),
                'search' => false,
            ),
            'lastname' => array(
                'title' => $this->trans('Lastname', array(), 'Admin.Global'),
                'search' => false,
            ),
            'firstname' => array(
                'title' => $this->trans('Firstname', array(), 'Admin.Global'),
                'search' => false,
            ),
            'email' => array(
                'title' => $this->trans('Email', array(), 'Admin.Global'),
                'search' => false,
            ),
            'subscribed' => array(
                'title' => $this->trans('Subscribed', array(), 'Modules.Emailsubscription.Admin'),
                'type' => 'bool',
                'active' => 'subscribed',
                'search' => false,
            ),
            'iso_code' => array(
                'title' => $this->trans('Iso language', array(), 'Modules.Emailsubscription.Admin'),
                'search' => false,
            ),
            'newsletter_date_add' => array(
                'title' => $this->trans('Subscribed on', array(), 'Modules.Emailsubscription.Admin'),
                'type' => 'date',
                'search' => false,
            ),
        );

        if (!Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            unset($fields_list['shop_name']);
        }

        $helper_list = new HelperList();
        $helper_list->module = $this;
        $helper_list->title = $this->trans('Newsletter registrations', array(), 'Modules.Emailsubscription.Admin');
        $helper_list->shopLinkType = '';
        $helper_list->no_link = true;
        $helper_list->show_toolbar = true;
        $helper_list->simple_header = false;
        $helper_list->identifier = 'id';
        $helper_list->table = 'merged';
        $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->actions = array('viewCustomer');

        // This is needed for displayEnableLink to avoid code duplication
        $this->_helperlist = $helper_list;

        /* Retrieve list data */
        $subscribers = $this->getSubscribers();
        $helper_list->listTotal = count($subscribers);

        /* Paginate the result */
        $page = ($page = Tools::getValue('submitFilter' . $helper_list->table)) ? $page : 1;
        $pagination = ($pagination = Tools::getValue($helper_list->table . '_pagination')) ? $pagination : 50;
        $subscribers = $this->paginateSubscribers($subscribers, $page, $pagination);

        return $helper_list->generateList($subscribers, $fields_list);
    }

    public function displayViewCustomerLink($token = null, $id, $name = null)
    {
        $this->smarty->assign(array(
            'href' => 'index.php?controller=AdminCustomers&id_customer=' . (int) $id . '&updatecustomer&token=' . Tools::getAdminTokenLite('AdminCustomers'),
            'action' => $this->trans('View', array(), 'Admin.Actions'),
            'disable' => !((int) $id > 0),
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_viewcustomer.tpl');
    }

    public function displayEnableLink($token, $id, $value, $active, $id_category = null, $id_product = null, $ajax = false)
    {
        $this->smarty->assign(array(
            'ajax' => $ajax,
            'enabled' => (bool) $value,
            'url_enable' => $this->_helperlist->currentIndex . '&' . $this->_helperlist->identifier . '=' . $id . '&' . $active . $this->_helperlist->table . ($ajax ? '&action=' . $active . $this->_helperlist->table . '&ajax=' . (int) $ajax : '') . ((int) $id_category && (int) $id_product ? '&id_category=' . (int) $id_category : '') . '&token=' . $token,
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_enable.tpl');
    }

    public function displayUnsubscribeLink($token = null, $id, $name = null)
    {
        $this->smarty->assign(array(
            'href' => $this->_helperlist->currentIndex . '&subscribedcustomer&' . $this->_helperlist->identifier . '=' . $id . '&token=' . $token,
            'action' => $this->trans('Unsubscribe', array(), 'Modules.Emailsubscription.Admin'),
        ));

        return $this->display(__FILE__, 'views/templates/admin/list_action_unsubscribe.tpl');
    }

    /**
     * Check if this mail is registered for newsletters.
     *
     * @param string $customer_email
     *
     * @return int -1 = not a customer and not registered
     *             0 = customer not registered
     *             1 = registered in block
     *             2 = registered in customer
     */
    public function isNewsletterRegistered($customer_email)
    {
        $sql = 'SELECT `email`
                FROM ' . _DB_PREFIX_ . 'emailsubscription
                WHERE `email` = \'' . pSQL($customer_email) . '\'
                AND id_shop = ' . $this->context->shop->id;

        if (Db::getInstance()->getRow($sql)) {
            return self::GUEST_REGISTERED;
        }

        $sql = 'SELECT `newsletter`
                FROM ' . _DB_PREFIX_ . 'customer
                WHERE `email` = \'' . pSQL($customer_email) . '\'
                AND id_shop = ' . $this->context->shop->id;

        if (!$registered = Db::getInstance()->getRow($sql)) {
            return self::GUEST_NOT_REGISTERED;
        }

        if ($registered['newsletter'] == '1') {
            return self::CUSTOMER_REGISTERED;
        }

        return self::CUSTOMER_NOT_REGISTERED;
    }

    /**
     * Register in email subscription.
     *
     * @param string|null $hookName For widgets displayed by a hook, hook name must be passed
     * as multiple hooks might be used, so it is necessary to know which one was used for
     * submitting the form
     *
     * @return bool|string
     */
    public function newsletterRegistration($hookName = null)
    {
        $isPrestaShopVersionOver177 = version_compare(_PS_VERSION_, '1.7.7', '>=');

        if ($isPrestaShopVersionOver177 && ($hookName !== null)) {
            if (empty($_POST['blockHookName']) || $_POST['blockHookName'] !== $hookName) {
                return false;
            }
        }

        // hook for newsletter registration/unregistration : fill-in hookError string is there is an error
        $hookError = null;
        Hook::exec(
            'actionNewsletterRegistrationBefore',
            [
                'hookName' => $hookName,
                'email' => $_POST['email'],
                'action' => $_POST['action'],
                'hookError' => &$hookError,
            ]
        );
        if ($hookError !== null) {
            return $this->error = $hookError;
        }

        if (empty($_POST['email']) || !Validate::isEmail($_POST['email'])) {
            return $this->error = $this->trans('Invalid email address.', array(), 'Shop.Notifications.Error');

        } elseif ($_POST['action'] == '1') {
            $register_status = $this->isNewsletterRegistered($_POST['email']);

            if ($register_status < 1) {
                return $this->error = $this->trans('This email address is not registered.', array(), 'Modules.Emailsubscription.Shop');
            }

            if (!$this->unregister($_POST['email'], $register_status)) {
                return $this->error = $this->trans('An error occurred while attempting to unsubscribe.', array(), 'Modules.Emailsubscription.Shop');
            }

            return $this->valid = $this->trans('Unsubscription successful.', array(), 'Modules.Emailsubscription.Shop');

        } elseif ($_POST['action'] == '0') {

            $register_status = $this->isNewsletterRegistered($_POST['email']);
            if ($register_status > 0) {
                return $this->error = $this->trans('This email address is already registered.', array(), 'Modules.Emailsubscription.Shop');
            }

            $email = pSQL($_POST['email']);
            if (!$this->isRegistered($register_status)) {
                if (Configuration::get('NW_VERIFICATION_EMAIL')) {

                    // create an unactive entry in the newsletter database
                    if ($register_status == self::GUEST_NOT_REGISTERED) {
                        $this->registerGuest($email, false);
                    }

                    if (!$token = $this->getToken($email, $register_status)) {
                        return $this->error = $this->trans('An error occurred during the subscription process.', array(), 'Modules.Emailsubscription.Shop');
                    }

                    $this->sendVerificationEmail($email, $token);

                    return $this->valid = $this->trans('A verification email has been sent. Please check your inbox.', array(), 'Modules.Emailsubscription.Shop');

                } else {
                    if ($this->register($email, $register_status)) {
                        $this->valid = $this->trans('You have successfully subscribed to this newsletter.', array(), 'Modules.Emailsubscription.Shop');
                    } else {
                        return $this->error = $this->trans('An error occurred during the subscription process.', array(), 'Modules.Emailsubscription.Shop');
                    }

                    if ($code = Configuration::get('NW_VOUCHER_CODE')) {
                        $this->sendVoucher($email, $code);
                    }

                    if (Configuration::get('NW_CONFIRMATION_EMAIL')) {
                        $this->sendConfirmationEmail($email);
                    }
                }
            }
        }
        // hook
        Hook::exec(
            'actionNewsletterRegistrationAfter',
            [
                'hookName' => $hookName,
                'email' => $_POST['email'],
                'action' => $_POST['action'],
                'error' => &$this->error,
            ]
        );
    }

    public function getSubscribers()
    {
        $dbquery = new DbQuery();
        $dbquery->select('c.`id_customer` AS `id`, s.`name` AS `shop_name`, gl.`name` AS `gender`, c.`lastname`, c.`firstname`, c.`email`, c.`newsletter` AS `subscribed`, c.`newsletter_date_add`, l.`iso_code`');
        $dbquery->from('customer', 'c');
        $dbquery->leftJoin('shop', 's', 's.id_shop = c.id_shop');
        $dbquery->leftJoin('gender', 'g', 'g.id_gender = c.id_gender');
        $dbquery->leftJoin('gender_lang', 'gl', 'g.id_gender = gl.id_gender AND gl.id_lang = ' . (int) $this->context->employee->id_lang);
        $dbquery->where('c.`newsletter` = 1');
        $dbquery->leftJoin('lang', 'l', 'l.id_lang = c.id_lang');
        if ($this->_searched_email) {
            $dbquery->where('c.`email` LIKE \'%' . pSQL($this->_searched_email) . '%\' ');
        }

        $customers = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());

        $dbquery = new DbQuery();
        $dbquery->select('CONCAT(\'N\', e.`id`) AS `id`, s.`name` AS `shop_name`, NULL AS `gender`, NULL AS `lastname`, NULL AS `firstname`, e.`email`, e.`active` AS `subscribed`, e.`newsletter_date_add`, l.`iso_code`');
        $dbquery->from('emailsubscription', 'e');
        $dbquery->leftJoin('shop', 's', 's.id_shop = e.id_shop');
        $dbquery->leftJoin('lang', 'l', 'l.id_lang = e.id_lang');
        $dbquery->where('e.`active` = 1');
        if ($this->_searched_email) {
            $dbquery->where('e.`email` LIKE \'%' . pSQL($this->_searched_email) . '%\' ');
        }

        $non_customers = Db::getInstance()->executeS($dbquery->build());

        $subscribers = array_merge($customers, $non_customers);

        return $subscribers;
    }

    public function paginateSubscribers($subscribers, $page = 1, $pagination = 50)
    {
        if (count($subscribers) > $pagination) {
            $subscribers = array_slice($subscribers, $pagination * ($page - 1), $pagination);
        }

        return $subscribers;
    }

    /**
     * Return true if the registered status correspond to a registered user.
     *
     * @param int $register_status
     *
     * @return bool
     */
    protected function isRegistered($register_status)
    {
        return in_array(
            $register_status,
            array(self::GUEST_REGISTERED, self::CUSTOMER_REGISTERED)
        );
    }

    /**
     * Subscribe an email to the newsletter. It will create an entry in the newsletter table
     * or update the customer table depending of the register status.
     *
     * @param string $email
     * @param int $register_status
     */
    protected function register($email, $register_status)
    {
        if ($register_status == self::GUEST_NOT_REGISTERED) {
            return $this->registerGuest($email);
        }

        if ($register_status == self::CUSTOMER_NOT_REGISTERED) {
            return $this->registerUser($email);
        }

        return false;
    }

    protected function unregister($email, $register_status)
    {
        if ($register_status == self::GUEST_REGISTERED) {
            $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE `email` = \'' . pSQL($_POST['email']) . '\' AND id_shop = ' . $this->context->shop->id;
        } elseif ($register_status == self::CUSTOMER_REGISTERED) {
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'customer SET `newsletter` = 0 WHERE `email` = \'' . pSQL($_POST['email']) . '\' AND id_shop = ' . $this->context->shop->id;
        }

        if (!isset($sql) || !Db::getInstance()->execute($sql)) {
            return false;
        }

        return true;
    }

    /**
     * Subscribe a customer to the newsletter.
     *
     * @param string $email
     *
     * @return bool
     */
    protected function registerUser($email)
    {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'customer
                SET `newsletter` = 1, newsletter_date_add = NOW(), `ip_registration_newsletter` = \'' . pSQL(Tools::getRemoteAddr()) . '\'
                WHERE `email` = \'' . pSQL($email) . '\'
                AND id_shop = ' . $this->context->shop->id;

        return Db::getInstance()->execute($sql);
    }

    /**
     * Subscribe a guest to the newsletter.
     *
     * @param string $email
     * @param bool $active
     *
     * @return bool
     */
    protected function registerGuest($email, $active = true)
    {
        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'emailsubscription (id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, http_referer, active, id_lang)
                VALUES
                (' . $this->context->shop->id . ',
                ' . $this->context->shop->id_shop_group . ',
                \'' . pSQL($email) . '\',
                NOW(),
                \'' . pSQL(Tools::getRemoteAddr()) . '\',
                (
                    SELECT c.http_referer
                    FROM ' . _DB_PREFIX_ . 'connections c
                    WHERE c.id_guest = ' . (int) $this->context->customer->id . '
                    ORDER BY c.date_add DESC LIMIT 1
                ),
                ' . (int) $active . ',
                ' . $this->context->language->id . '
                )';

        return Db::getInstance()->execute($sql);
    }

    public function activateGuest($email)
    {
        return Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'emailsubscription`
                        SET `active` = 1
                        WHERE `email` = \'' . pSQL($email) . '\''
        );
    }

    /**
     * Returns a guest email by token.
     *
     * @param string $token
     *
     * @return string email
     */
    protected function getGuestEmailByToken($token)
    {
        $sql = 'SELECT `email`
                FROM `' . _DB_PREFIX_ . 'emailsubscription`
                WHERE MD5(CONCAT( `email` , `newsletter_date_add`, \'' . pSQL(Configuration::get('NW_SALT')) . '\')) = \'' . pSQL($token) . '\'
                AND `active` = 0';

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Returns a customer email by token.
     *
     * @param string $token
     *
     * @return string email
     */
    protected function getUserEmailByToken($token)
    {
        $sql = 'SELECT `email`
                FROM `' . _DB_PREFIX_ . 'customer`
                WHERE MD5(CONCAT( `email` , `date_add`, \'' . pSQL(Configuration::get('NW_SALT')) . '\')) = \'' . pSQL($token) . '\'
                AND `newsletter` = 0';

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Return a token associated to an user.
     *
     * @param string $email
     * @param string $register_status
     */
    protected function getToken($email, $register_status)
    {
        if (in_array($register_status, array(self::GUEST_NOT_REGISTERED, self::GUEST_REGISTERED))) {
            $sql = 'SELECT MD5(CONCAT( `email` , `newsletter_date_add`, \'' . pSQL(Configuration::get('NW_SALT')) . '\')) as token
                    FROM `' . _DB_PREFIX_ . 'emailsubscription`
                    WHERE `active` = 0
                    AND `email` = \'' . pSQL($email) . '\'';
        } elseif ($register_status == self::CUSTOMER_NOT_REGISTERED) {
            $sql = 'SELECT MD5(CONCAT( `email` , `date_add`, \'' . pSQL(Configuration::get('NW_SALT')) . '\' )) as token
                    FROM `' . _DB_PREFIX_ . 'customer`
                    WHERE `newsletter` = 0
                    AND `email` = \'' . pSQL($email) . '\'';
        }

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Ends the registration process to the newsletter.
     *
     * @param string $token
     *
     * @return string
     */
    public function confirmEmail($token)
    {
        $activated = false;

        if ($email = $this->getGuestEmailByToken($token)) {
            $activated = $this->activateGuest($email);
        } elseif ($email = $this->getUserEmailByToken($token)) {
            $activated = $this->registerUser($email);
        }

        if (!$activated) {
            return $this->trans('This email is already registered and/or invalid.', array(), 'Modules.Emailsubscription.Shop');
        }

        if ($discount = Configuration::get('NW_VOUCHER_CODE')) {
            $this->sendVoucher($email, $discount);
        }

        if (Configuration::get('NW_CONFIRMATION_EMAIL')) {
            $this->sendConfirmationEmail($email);
        }

        return $this->trans('Thank you for subscribing to our newsletter.', array(), 'Modules.Emailsubscription.Shop');
    }

    /**
     * Send the confirmation mails to the given $email address if needed.
     *
     * @param string $email Email where to send the confirmation
     *
     * @note the email has been verified and might not yet been registered. Called by AuthController::processCustomerNewsletter
     */
    public function confirmSubscription($email)
    {
        if ($email) {
            if ($discount = Configuration::get('NW_VOUCHER_CODE')) {
                $this->sendVoucher($email, $discount);
            }

            if (Configuration::get('NW_CONFIRMATION_EMAIL')) {
                $this->sendConfirmationEmail($email);
            }
        }
    }

    /**
     * Send an email containing a voucher code.
     *
     * @param $email
     * @param $code
     *
     * @return bool|int
     */
    protected function sendVoucher($email, $code)
    {
        $language = new Language($this->context->language->id);

        return Mail::Send(
            $this->context->language->id,
            'newsletter_voucher',
            $this->trans(
                'Newsletter voucher',
                array(),
                'Emails.Subject',
                $language->locale
            ),
            array(
                '{discount}' => $code,
            ),
            $email,
            null,
            null,
            null,
            null,
            null,
            dirname(__FILE__) . '/mails/',
            false,
            $this->context->shop->id
        );
    }

    /**
     * Send a confirmation email.
     *
     * @param string $email
     *
     * @return bool
     */
    protected function sendConfirmationEmail($email)
    {
        $language = new Language($this->context->language->id);

        return Mail::Send(
            $this->context->language->id,
            'newsletter_conf',
            $this->trans(
                'Newsletter confirmation',
                array(),
                'Emails.Subject',
                $language->locale
            ),
            array(),
            pSQL($email),
            null,
            null,
            null,
            null,
            null,
            dirname(__FILE__) . '/mails/',
            false,
            $this->context->shop->id
        );
    }

    /**
     * Send a verification email.
     *
     * @param string $email
     * @param string $token
     *
     * @return bool
     */
    protected function sendVerificationEmail($email, $token)
    {
        $verif_url = Context::getContext()->link->getModuleLink(
            'ps_emailsubscription', 'verification', array(
                'token' => $token,
            )
        );
        $language = new Language($this->context->language->id);

        return Mail::Send(
            $this->context->language->id,
            'newsletter_verif',
            $this->trans(
                'Email verification',
                array(),
                'Emails.Subject',
                $language->locale
            ),
            array(
                '{verif_url}' => $verif_url,
            ),
            $email,
            null,
            null,
            null,
            null,
            null,
            dirname(__FILE__) . '/mails/',
            false,
            $this->context->shop->id
        );
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        if ($hookName == null && isset($configuration['hook'])) {
            $hookName = $configuration['hook'];
        }

        $template_file = ($hookName == 'displayLeftColumn') ? self::TPL_COLUMN : self::TPL_DEFAULT;
        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        $this->context->smarty->assign(array(
            'id_module' => $this->id,
            'hookName' => $hookName,
        ));

        return $this->fetch('module:ps_emailsubscription/views/templates/hook/' . $template_file);
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $variables = [];
        $variables['value'] = '';
        $variables['msg'] = '';
        $variables['conditions'] = Configuration::get('NW_CONDITIONS', $this->context->language->id);

        if (Tools::isSubmit('submitNewsletter')) {
            $this->error = $this->valid = '';
            $this->newsletterRegistration($hookName);

            if ($this->error) {
                $variables['value'] = Tools::getValue('email', '');
                $variables['msg'] = $this->error;
                $variables['nw_error'] = true;
            } elseif ($this->valid) {
                $variables['value'] = Tools::getValue('email', '');
                $variables['msg'] = $this->valid;
                $variables['nw_error'] = false;
            }
        }

        return $variables;
    }

    public function hookActionFrontControllerSetMedia()
    {
        Media::addJsDef([
            'psemailsubscription_subscription' => $this->context->link->getModuleLink($this->name, 'subscription', [], true),
        ]);

        $this->context->controller->registerJavascript('modules-psemailsubscription', 'modules/' . $this->name . '/views/js/ps_emailsubscription.js');
    }

    /**
     * Deletes duplicates email in newsletter table.
     *
     * @param $params
     *
     * @return bool
     */
    public function hookActionCustomerAccountAdd($params)
    {
        //if e-mail of the created user address has already been added to the newsletter through the ps_emailsubscription module,
        //we delete it from ps_emailsubscription table to prevent duplicates
        if (empty($params['newCustomer'])) {
            return;
        }
        $id_shop = $params['newCustomer']->id_shop;
        $email = $params['newCustomer']->email;
        $newsletter = $params['newCustomer']->newsletter;
        if (Validate::isEmail($email)) {
            if ($params['newCustomer']->newsletter && $code = Configuration::get('NW_VOUCHER_CODE')) {
                $this->sendVoucher($email, $code);
            }

            return (bool) Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE id_shop=' . (int) $id_shop . ' AND email=\'' . pSQL($email) . "'");
        }

        if ($newsletter) {
            if (Configuration::get('NW_CONFIRMATION_EMAIL')) {// send confirmation email
                $this->sendConfirmationEmail($params['newCustomer']->email);
            }
            if ($code = Configuration::get('NW_VOUCHER_CODE')) {// send voucher
                $this->sendVoucher($params['newCustomer']->email, $code);
            }
        }

        return true;
    }

   public function hookActionObjectCustomerUpdateBefore($params)
   {
       $customer = new Customer($params['object']->id);
       $this->_origin_newsletter = (int)$customer->newsletter;
   }

    public function hookActionCustomerAccountUpdate($params)
    {
        if ($this->_origin_newsletter || !$params['customer']->newsletter) {
            return;
        }
        if (Configuration::get('NW_CONFIRMATION_EMAIL')) {// send confirmation email
            $this->sendConfirmationEmail($params['customer']->email);
        }
        if ($code = Configuration::get('NW_VOUCHER_CODE')) {
            $cartRule = CartRuleCore::getCartsRuleByCode($code, Context::getContext()->language->id);
            if (! Order::getDiscountsCustomer($params['customer']->id, $cartRule[0])) {// send voucher
                $this->sendVoucher($params['customer']->email, $code);
            }
        }

        return true;
    }

    /**
     * Add an extra FormField to ask for newsletter registration.
     *
     * @param $params
     *
     * @return bool
     */
    public function hookAdditionalCustomerFormFields($params)
    {
        $label = $this->trans(
            'Sign up for our newsletter[1][2]%conditions%[/2]',
            array(
                '[1]' => '<br>',
                '[2]' => '<em>',
                '%conditions%' => Configuration::get('NW_CONDITIONS', $this->context->language->id),
                '[/2]' => '</em>',
            ),
            'Modules.Emailsubscription.Shop'
        );

        return array(
            (new FormField())
                ->setName('newsletter')
                ->setType('checkbox')
                ->setLabel($label), );
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Settings', array(), 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Would you like to send a verification email after subscription?', array(), 'Modules.Emailsubscription.Admin'),
                        'name' => 'NW_VERIFICATION_EMAIL',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', array(), 'Admin.Global'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Would you like to send a confirmation email after subscription?', array(), 'Modules.Emailsubscription.Admin'),
                        'name' => 'NW_CONFIRMATION_EMAIL',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', array(), 'Admin.Global'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Welcome voucher code', array(), 'Modules.Emailsubscription.Admin'),
                        'name' => 'NW_VOUCHER_CODE',
                        'class' => 'fixed-width-md',
                        'desc' => $this->trans('Leave blank to disable by default.', array(), 'Modules.Emailsubscription.Admin'),
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Newsletter conditions', array(), 'Modules.Emailsubscription.Admin'),
                        'lang' => true,
                        'name' => 'NW_CONDITIONS',
                        'cols' => 40,
                        'rows' => 100,
                        'hint' => $this->trans(
                            'This text will be displayed beneath the newsletter subscribe button.',
                            array(),
                            'Modules.Emailsubscription.Admin'
                        ),
                        'desc' => $this->trans('Leave blank to disable by default.', array(), 'Modules.Emailsubscription.Admin'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitUpdate';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function renderExportForm()
    {
        // Getting data...
        $countries = Country::getCountries($this->context->language->id);

        // ...formatting array
        $countries_list = array(array('id' => 0, 'name' => $this->trans('All countries', array(), 'Admin.Global')));
        foreach ($countries as $country) {
            $countries_list[] = array('id' => $country['id_country'], 'name' => $country['name']);
        }

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Export customers\' addresses', array(), 'Modules.Emailsubscription.Admin'),
                    'icon' => 'icon-envelope',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Customers\' country', array(), 'Modules.Emailsubscription.Admin'),
                        'desc' => $this->trans('Filter customers by country.', array(), 'Modules.Emailsubscription.Admin'),
                        'name' => 'COUNTRY',
                        'required' => false,
                        'default_value' => (int) $this->context->country->id,
                        'options' => array(
                            'query' => $countries_list,
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Newsletter subscribers', array(), 'Modules.Emailsubscription.Admin'),
                        'desc' => $this->trans('Filter customers who have subscribed to the newsletter or not, and who have an account or not.', array(), 'Modules.Emailsubscription.Admin'),
                        'hint' => $this->trans('Customers can subscribe to your newsletter when registering, or by entering their email in the newsletter form.', array(), 'Modules.Emailsubscription.Admin'),
                        'name' => 'SUSCRIBERS',
                        'required' => false,
                        'default_value' => 1,
                        'options' => array(
                            'query' => array(
                                array('id' => 0, 'name' => $this->trans('All subscribers', array(), 'Modules.Emailsubscription.Admin')),
                                array('id' => 1, 'name' => $this->trans('Subscribers with account', array(), 'Modules.Emailsubscription.Admin')),
                                array('id' => 2, 'name' => $this->trans('Subscribers without account', array(), 'Modules.Emailsubscription.Admin')),
                                array('id' => 3, 'name' => $this->trans('Non-subscribers', array(), 'Modules.Emailsubscription.Admin')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Partner offers subscribers', array(), 'Modules.Emailsubscription.Admin'),
                        'desc' => $this->trans('Filter customers who have agreed to receive your partners\' offers or not.', array(), 'Modules.Emailsubscription.Admin'),
                        'hint' => $this->trans('Partner offers subscribers have agreed to receive your partners\' offers.', array(), 'Modules.Emailsubscription.Admin'),
                        'name' => 'OPTIN',
                        'required' => false,
                        'default_value' => 1,
                        'options' => array(
                            'query' => array(
                                array('id' => 0, 'name' => $this->trans('All customers', array(), 'Modules.Emailsubscription.Admin')),
                                array('id' => 2, 'name' => $this->trans('Partner offers subscribers', array(), 'Modules.Emailsubscription.Admin')),
                                array('id' => 1, 'name' => $this->trans('Partner offers non-subscribers', array(), 'Modules.Emailsubscription.Admin')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'hidden',
                        'name' => 'action',
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Export .CSV file', array(), 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitExport',
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function renderSearchForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Search for addresses', array(), 'Modules.Emailsubscription.Admin'),
                    'icon' => 'icon-search',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Email address to search', array(), 'Modules.Emailsubscription.Admin'),
                        'name' => 'searched_email',
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->trans('Example: contact@prestashop.com or @prestashop.com', array(), 'Modules.Emailsubscription.Admin'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Search', array(), 'Admin.Actions'),
                    'icon' => 'process-icon-refresh',
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->table = $this->table;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'searchEmail';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => array('searched_email' => $this->_searched_email),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        $conditions = array();
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $conditions[$lang['id_lang']] = Tools::getValue(
                'NW_CONDITIONS_' . $lang['id_lang'],
                Configuration::get('NW_CONDITIONS', $lang['id_lang']
                )
            );
        }

        return array(
            'NW_VERIFICATION_EMAIL' => Tools::getValue('NW_VERIFICATION_EMAIL', Configuration::get('NW_VERIFICATION_EMAIL')),
            'NW_CONFIRMATION_EMAIL' => Tools::getValue('NW_CONFIRMATION_EMAIL', Configuration::get('NW_CONFIRMATION_EMAIL')),
            'NW_VOUCHER_CODE' => Tools::getValue('NW_VOUCHER_CODE', Configuration::get('NW_VOUCHER_CODE')),
            'NW_CONDITIONS' => $conditions,
            'COUNTRY' => Tools::getValue('COUNTRY'),
            'SUSCRIBERS' => Tools::getValue('SUSCRIBERS'),
            'OPTIN' => Tools::getValue('OPTIN'),
            'action' => 'customers',
        );
    }

    public function export_csv()
    {
        if (!isset($this->context)) {
            $this->context = Context::getContext();
        }

        $result = $this->getCustomers();

        if ($result) {
            if (!$nb = count($result)) {
                $this->_html .= $this->displayError($this->trans('No customers found with these filters!', array(), 'Modules.Emailsubscription.Admin'));

            } elseif ($fd = @fopen(dirname(__FILE__) . '/' . strval(preg_replace('#\.{2,}#', '.', Tools::getValue('action'))) . '_' . $this->file, 'w')) {
                $header = array('id', 'shop_name', 'gender', 'lastname', 'firstname', 'email', 'subscribed', 'subscribed_on', 'iso_language');
                $array_to_export = array_merge(array($header), $result);

                foreach ($array_to_export as $tab) {
                    $this->myFputCsv($fd, $tab);
                }

                fclose($fd);

                $this->_html .= $this->displayConfirmation(
                    sprintf($this->trans('The .CSV file has been successfully exported: %d customers found.', array(), 'Modules.Emailsubscription.Admin'), $nb) . '<br />
                <a href="' . $this->context->shop->getBaseURI() . 'modules/ps_emailsubscription/' . Tools::safeOutput(strval(Tools::getValue('action'))) . '_' . $this->file . '">
                <b>' . $this->trans('Download the file', array(), 'Modules.Emailsubscription.Admin') . ' ' . $this->file . '</b>
                </a>
                <br />
                <ol style="margin-top: 10px;">
                    <li style="color: red;">' .
                    $this->trans('WARNING: When opening this .csv file with Excel, choose UTF-8 encoding to avoid strange characters.', array(), 'Modules.Emailsubscription.Admin') .
                    '</li>
                </ol>');
            } else {
                $this->_html .= $this->displayError($this->trans('Error: Write access limited', array(), 'Modules.Emailsubscription.Admin') . ' ' . dirname(__FILE__) . '/' . strval(Tools::getValue('action')) . '_' . $this->file . ' !');
            }
        } else {
            $this->_html .= $this->displayError($this->trans('No result found!', array(), 'Modules.Emailsubscription.Admin'));
        }
    }

    private function getCMSRoles()
    {
        $cms_repository = $this->entity_manager->getRepository('CMS');
        $id_lang = Context::getContext()->employee->id_lang;
        $id_shop = Context::getContext()->shop->id;
        $cms_pages = array();

        $fake_object = new stdClass();
        $fake_object->id = 0;
        $fake_object->name = $this->trans('-- Select associated page --', array(), 'Modules.Emailsubscription.Admin');
        $cms_pages[-1] = $fake_object;
        unset($fake_object);

        foreach ($cms_repository->i10nFindAll($id_lang, $id_shop) as $cms_page) {
            $object = new stdClass();
            $object->id = $cms_page->id;
            $object->name = $cms_page->meta_title;
            $cms_pages[] = $object;
        }

        return $cms_pages;
    }

    private function getCustomers()
    {
        $id_shop = false;

        // Get the value to know with subscrib I need to take 1 with account 2 without 0 both 3 not subscrib
        $who = (int) Tools::getValue('SUSCRIBERS');

        // get optin 0 for all 1 no optin 2 with optin
        $optin = (int) Tools::getValue('OPTIN');

        $country = (int) Tools::getValue('COUNTRY');

        if (Context::getContext()->cookie->shopContext) {
            $id_shop = (int) Context::getContext()->shop->id;
        }

        $customers = array();
        if ($who == 1 || $who == 0 || $who == 3) {
            $dbquery = new DbQuery();
            $dbquery->select('c.`id_customer` AS `id`, s.`name` AS `shop_name`, gl.`name` AS `gender`, c.`lastname`, c.`firstname`, c.`email`, c.`newsletter` AS `subscribed`, c.`newsletter_date_add`, l.`iso_code`');
            $dbquery->from('customer', 'c');
            $dbquery->leftJoin('shop', 's', 's.id_shop = c.id_shop');
            $dbquery->leftJoin('gender', 'g', 'g.id_gender = c.id_gender');
            $dbquery->leftJoin('gender_lang', 'gl', 'g.id_gender = gl.id_gender AND gl.id_lang = ' . $this->context->employee->id_lang);
            $dbquery->where('c.`newsletter` = ' . ($who == 3 ? 0 : 1));
            $dbquery->leftJoin('lang', 'l', 'l.id_lang = c.id_lang');
            if ($optin == 2 || $optin == 1) {
                $dbquery->where('c.`optin` = ' . ($optin == 1 ? 0 : 1));
            }
            if ($country) {
                $dbquery->where('(SELECT COUNT(a.`id_address`) as nb_country
                                                    FROM `' . _DB_PREFIX_ . 'address` a
                                                    WHERE a.deleted = 0
                                                    AND a.`id_customer` = c.`id_customer`
                                                    AND a.`id_country` = ' . $country . ') >= 1');
            }
            if ($id_shop) {
                $dbquery->where('c.`id_shop` = ' . $id_shop);
            }

            $customers = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());
        }

        $non_customers = array();
        if (($who == 0 || $who == 2) && (!$optin || $optin == 2) && !$country) {
            $dbquery = new DbQuery();
            $dbquery->select('CONCAT(\'N\', e.`id`) AS `id`, s.`name` AS `shop_name`, NULL AS `gender`, NULL AS `lastname`, NULL AS `firstname`, e.`email`, e.`active` AS `subscribed`, e.`newsletter_date_add`, l.`iso_code`');
            $dbquery->from('emailsubscription', 'e');
            $dbquery->leftJoin('shop', 's', 's.id_shop = e.id_shop');
            $dbquery->where('e.`active` = 1');
            $dbquery->leftJoin('lang', 'l', 'l.id_lang = e.id_lang');
            if ($id_shop) {
                $dbquery->where('e.`id_shop` = ' . $id_shop);
            }
            $non_customers = Db::getInstance()->executeS($dbquery->build());
        }

        $subscribers = array_merge($customers, $non_customers);

        return $subscribers;
    }

    private function myFputCsv($fd, $array)
    {
        $line = implode(';', $array);
        $line .= "\n";
        if (!fwrite($fd, $line, 4096)) {
            $this->post_errors[] = $this->trans('Error: Write access limited', array(), 'Modules.Emailsubscription.Admin') . ' ' . dirname(__FILE__) . '/' . $this->file . ' !';
        }
    }

    private function getConditionFixtures($lang)
    {
        $locale = $lang['locale'];

        return
            $this->trans('You may unsubscribe at any moment. For that purpose, please find our contact info in the legal notice.', array(), 'Modules.Emailsubscription.Shop', $locale)
        ;
    }

    /**
     * This hook allow you to add new fields in the admin customer form
     *
     * @return string
     */
    public function hookDisplayAdminCustomersForm()
    {
        $newsletter = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT `newsletter`
            FROM ' . _DB_PREFIX_ . 'customer
            WHERE `id_customer` = ' . (int) Tools::getValue('id_customer', 0));

        $input = array(
            'type' => 'switch',
            'label' => $this->trans('Newsletter', array(), 'Admin.Orderscustomers.Feature'),
            'name' => 'newsletter',
            'required' => false,
            'class' => 't',
            'is_bool' => true,
            'value' => $newsletter,
            'values' => array(
                array(
                    'id' => 'newsletter_on',
                    'value' => 1,
                    'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                ),
                array(
                    'id' => 'newsletter_off',
                    'value' => 0,
                    'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                ),
            ),
            'hint' => $this->trans('This customer will receive your newsletter via email.', array(), 'Admin.Orderscustomers.Help'),
        );
        $this->context->smarty->assign(array('input' => $input));

        return $this->display(__FILE__, 'views/templates/admin/newsletter_subscribe.tpl');
    }

    public function hookActionDeleteGDPRCustomer($customer)
    {
        if (!empty($customer['email']) && Validate::isEmail($customer['email'])) {
            $sql = 'DELETE FROM ' . _DB_PREFIX_ . "emailsubscription WHERE email = '" . pSQL($customer['email']) . "'";
            if (Db::getInstance()->execute($sql)) {
                return json_encode(true);
            }

            return json_encode($this->trans('Newsletter subscription: no email to delete, this customer has not registered.', array(), 'Modules.Emailsubscription.Admin'));
        }
    }

    public function hookActionExportGDPRData($customer)
    {
        if (!Tools::isEmpty($customer['email']) && Validate::isEmail($customer['email'])) {
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . "emailsubscription WHERE email = '" . pSQL($customer['email']) . "'";
            if ($res = Db::getInstance()->executeS($sql)) {
                return json_encode($res);
            }

            return json_encode($this->trans('Newsletter subscription: no email to export, this customer has not registered.', array(), 'Modules.Emailsubscription.Admin'));
        }
    }
}
