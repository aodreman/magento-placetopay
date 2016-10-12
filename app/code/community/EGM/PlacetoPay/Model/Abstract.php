<?php

use Dnetix\Redirection\Entities\Person;
use Dnetix\Redirection\Entities\Status;
use Dnetix\Redirection\Message\RedirectRequest;
use Dnetix\Redirection\Message\RedirectResponse;
use Dnetix\Redirection\PlacetoPay;
use Dnetix\Redirection\Validators\Currency;

require_once(__DIR__ . '/../bootstrap.php');

/**
 * Procesa las peticiones de PlacetoPay, generando las tramas e interpretandolas
 *
 * @category   EGM
 * @package    EGM_PlacetoPay
 * @author     Enrique Garcia M. <ingenieria@egm.co>
 * @since      martes, 17 de noviembre de 2009
 */
abstract class EGM_PlacetoPay_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{
    const VERSION = '2.0.0';
    const WS_URL = 'http://redirection.p2p.dev/soap/redirect';

    /**
     * unique internal payment method identifier
     */
    protected $_code = 'placetopay_abstract';

    protected $_formBlockType = 'placetopay/form';
    protected $_infoBlockType = 'placetopay/info';

    /**
     * Opciones de disponiblidad
     */
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;

    protected $_defaultLocale = 'es';
    protected $_supportedLocales = ['en', 'es', 'fr'];
    protected $_p2pStatus = self::STATUS_UNKNOWN;

    /*
     * @var Mage_Sales_Model_Order
     */
    protected $_order;
    protected $gateway;

    /**
     * Determina si puede procesar usando la moneda
     *
     * @param string $currencyCode
     * @return boolean
     */
    public function canUseForCurrency($currencyCode)
    {
        return Currency::isValidCurrency($currencyCode);
    }

    public function isInitializeNeeded()
    {
        return true;
    }

    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_UNKNOWN);
        $stateObject->setIsNotified(false);
        return $this;
    }

    /**
     * @return EGM_PlacetoPay_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('placetopay/session');
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return EGM_PlacetoPay_Model_Info
     */
    public function getInfoModel()
    {
        return Mage::getModel('placetopay/info');
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!isset($this->_order)) {
            $this->_order = Mage::getModel('sales/order');
            $this->_order->loadByIncrementId($this->getCheckout()->getLastRealOrderId());
        }
        return $this->_order;
    }

    public static function getModuleConfig($key)
    {
        return Mage::getStoreConfig('placetopay/' . $key);
    }

    public function getConfig($key)
    {
        return Mage::getStoreConfig('payment/' . $this->_code . '/' . $key);
    }

    public static function trans($value)
    {
        return Mage::helper('placetopay')->__($value);
    }

    /**
     * Retorna la version del componente
     * @return string
     */
    function getVersion()
    {
        return 'PlacetoPay PHP Component ' . self::VERSION;
    }

    /**
     * URL a la cual ir una vez se pone la orden
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('placetopay/processing/redirect', ['_secure' => true]);
    }

    /**
     * @param string $name
     * @return EGM_PlacetoPay_Block_Form
     */
    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('placetopay/form', $name)
            ->setMethod($this->getMethod())
            ->setPayment($this->getPayment())
            ->setTemplate('placetopay/form.phtml');

        return $block;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return Status
     */
    public function parseOrderState($order)
    {
        $status = null;
        switch ($order->getStatus()){
            case Mage_Sales_Model_Order::STATE_PROCESSING:
                $status = Status::ST_APPROVED;
                break;
            case Mage_Sales_Model_Order::STATE_CANCELED:
                $status = Status::ST_REJECTED;
                break;
            case Mage_Sales_Model_Order::STATE_NEW:
                $status = Status::ST_PENDING;
                break;
            default:
                $status = Status::ST_PENDING;
        }
        return new Status([
            'status' => $status
        ]);
    }

    /**
     * @return PlacetoPay
     */
    public function gateway()
    {
        if (!$this->gateway) {
            $this->gateway = new PlacetoPay([
                'login' => $this->getConfig('login'),
                'tranKey' => $this->getConfig('trankey'),
                'location' => self::WS_URL,
            ]);
        }
        return $this->gateway;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Checkout_Model_Session $checkout
     * @return RedirectResponse
     */
    public function getPaymentRedirect($order, $checkout)
    {
        $request = $this->getRedirectRequestFromOrder($order, $checkout);
        return $this->gateway()->request($request);
    }

    /**
     * @param  Mage_Sales_Model_Order $order
     * @return string
     */
    public function getCheckoutRedirect($order)
    {
        $this->_order = $order;
        $response = $this->getPaymentRedirect($order, $this->getCheckout());

        if ($response->isSuccessful()) {
            $payment = $order->getPayment();
            $info = $this->getInfoModel();

            $info->loadInformationFromRedirectResponse($payment, $response);
        } else {
            Mage::log($response->status()->reason() . '-' . $response->status()->message());
            Mage::throwException(Mage::helper('placetopay')->__($response->status()->message()));
        }

        return $response->processUrl();
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Checkout_Model_Session $checkout
     * @return RedirectRequest
     */
    public function getRedirectRequestFromOrder($order, $checkout)
    {
        $reference = $checkout->getLastRealOrderId();
        $total = $order->getTotalDue();
        $discount = $order->getDiscountAmount();
        $taxAmount = $order->getTaxAmount();
        $shipping = $order->getShippingAmount();

        if (!$taxAmount || (int)$taxAmount === 0)
            $devolutionBase = 0;
        else
            $devolutionBase = $total - $taxAmount - $shipping;

        $subtotal = $total - $taxAmount - $shipping - $discount;

        /**
         * @var Mage_Sales_Model_Order_Item[] $visibleItems
         */
        $visibleItems = $order->getAllVisibleItems();
        $items = [];
        foreach ($visibleItems as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'category' => $item->getProductType(),
                'qty' => $item->getQtyOrdered(),
                'price' => $item->getPrice(),
                'tax' => $item->getTaxAmount(),
            ];
        }

        $data = [
            'locale' => Mage::app()->getLocale()->getLocaleCode(),
            'buyer' => $this->parseAddressPerson($order->getBillingAddress()),
            'payment' => [
                'reference' => $reference,
                'description' => $this->getConfig('description'),
                'amount' => [
                    'taxes' => [
                        [
                            'kind' => 'valueAddedTax',
                            'amount' => $taxAmount,
                        ],
                    ],
                    'details' => [
                        [
                            'kind' => 'subtotal',
                            'amount' => $subtotal,
                        ],
                        [
                            'kind' => 'discount',
                            'amount' => $discount,
                        ],
                        [
                            'kind' => 'shipping',
                            'amount' => $shipping,
                        ],
                        [
                            'kind' => 'vatDevolutionBase',
                            'amount' => $devolutionBase,
                        ],
                    ],
                    'currency' => $order->getOrderCurrencyCode(),
                    'total' => $total,
                ],
                'items' => $items,
                'shipping' => $this->parseAddressPerson($order->getShippingAddress()),
            ],
            'returnUrl' => Mage::getUrl('placetopay/processing/response') . '?reference=' . $reference,
            'expiration' => date('c', strtotime('+2 days')),
            'ipAddress' => Mage::helper('core/http')->getRemoteAddr(),
            'userAgent' => Mage::helper('core/http')->getHttpUserAgent(),
        ];

        return new RedirectRequest($data);
    }

    /**
     * @param $documentType
     * @return string|null
     */
    public function parseDocumentType($documentType)
    {
        $documentTypes = [
            '1' => 'CC',
            '2' => 'CE',
            '3' => 'NIT',
            '4' => 'TI',
            '5' => 'PPN',
            '6' => null,
            '7' => 'SSN',
            '8' => 'LIC',
            '9' => 'TAX',
        ];
        return isset($documentTypes[$documentType]) ? $documentTypes[$documentType] : null;
    }

    /**
     * @param Mage_Sales_Model_Order_Address $address
     * @return Person
     */
    public function parseAddressPerson($address)
    {
        return new Person([
            'name' => $address->getFirstname(),
            'surname' => $address->getLastname(),
            'email' => $address->getEmail(),
            'address' => [
                'country' => $address->getCountryId(),
                'state' => $address->getRegion(),
                'city' => $address->getCity(),
                'street' => implode(' ', $address->getStreet()),
                'phone' => $address->getTelephone(),
                'postalCode' => $address->getPostcode(),
            ],
        ]);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isPendingOrder($order)
    {
        return $order->getStatus() == 'pending' || $order->getStatus() == 'pending_payment';
    }

    /**
     * Crea la factura para la orden
     * @param Mage_Sales_Model_Order $order
     */
    protected function _createInvoice($order)
    {
        if (!$order->canInvoice())
            return;
        $invoice = $order->prepareInvoice();
        $invoice->register()->capture();
        $order->addRelatedObject($invoice);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \Dnetix\Redirection\Message\RedirectInformation
     */
    public function resolve($order, $payment = null)
    {
        if (!$payment)
            $payment = $order->getPayment();

        $info = $payment->getAdditionalInformation();

        if (!$info || !isset($info['request_id']))
            Mage::throwException('No additional information for this order:' . $order->getRealOrderId());

        $response = $this->gateway()->query($info['request_id']);

        if ($response->isSuccessful()) {
            $this->settleOrderStatus($response->status(), $order);
        }

        return $response;
    }

    /**
     * @param Status $status
     * @param Mage_Sales_Model_Order $order
     */
    public function settleOrderStatus(Status $status, &$order, $payment = null)
    {
        switch ($status->status()) {
            case Status::ST_APPROVED:
                $comment = self::trans('transaction_approved');
                $state = Mage_Sales_Model_Order::STATE_PROCESSING;
                $orderStatus = Mage_Sales_Model_Order::STATE_PROCESSING;
                break;
            case Status::ST_REJECTED:
                $comment = self::trans('transaction_rejected');
                $state = Mage_Sales_Model_Order::STATE_CANCELED;
                $orderStatus = Mage_Sales_Model_Order::STATE_CANCELED;
                break;
            case Status::ST_PENDING:
                $comment = self::trans('transaction_pending');
                $state = Mage_Sales_Model_Order::STATE_NEW;
                $orderStatus = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
                break;
            default:
                $state = $orderStatus = $comment = null;
        }

        if ($state !== null) {
            if (!$payment)
                $payment = $order->getPayment();

            if ($status->isApproved()) {
                $this->_createInvoice($order);
                $order->sendNewOrderEmail()
                    ->setEmailSent(true);
                $order->setState($state, $orderStatus, $comment)
                    ->save();
            } else if ($status->isRejected()) {
                $order->cancel()
                    ->save();
            } else {
                $order->setState($state, $orderStatus, $comment)
                    ->save();
            }
        }
    }

    /**
     * Asienta el pago
     * @param Mage_Sales_Model_Order $order
     * @param PlacetoPay $p2p
     */
    public function settlePayment(Mage_Sales_Model_Order $order)
    {
        $wasCancelled = false;
        switch ($p2p->responseCode()) {
            case PlacetoPay::P2P_ERROR:
                if ($order->getStatus() != Mage_Sales_Model_Order::STATE_CANCELED) {
                    $comment = Mage::helper('placetopay')->__('Transaction Failed');
                    $state = Mage_Sales_Model_Order::STATE_CANCELED;
                    $status = Mage_Payment_Model_Method_Abstract::STATUS_ERROR;
                }
                break;
            case PlacetoPay::P2P_DECLINED:
                if ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED) {
                    $comment = Mage::helper('placetopay')->__('Transaction Rejected');
                    $state = Mage_Sales_Model_Order::STATE_CANCELED;
                    $status = Mage_Payment_Model_Method_Abstract::STATUS_DECLINED;
                }
                break;
            case PlacetoPay::P2P_APPROVED:
            case PlacetoPay::P2P_DUPLICATE:
                // verifica que no se haya completado para no reprocesar el pedido
                if ($order->getState() != Mage_Sales_Model_Order::STATE_PROCESSING) {
                    $comment = Mage::helper('placetopay')->__('Transaction Approved');
                    $state = Mage_Sales_Model_Order::STATE_PROCESSING;
                    $status = Mage_Payment_Model_Method_Abstract::STATUS_APPROVED;
                }
                if ($order->getState() == Mage_Sales_Model_Order::STATE_CANCELED) {
                    $wasCancelled = true;
                }

                break;
            case PlacetoPay::P2P_PENDING:
                if (($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) || ($order->getState() == Mage_Sales_Model_Order::STATE_NEW)) {
                    $comment = Mage::helper('placetopay')->__('Transaction Pending');
                    $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
                    $status = Mage_Payment_Model_Method_Abstract::STATUS_UNKNOWN;
                }
                break;
        }

        // determina si realiza la actualizacion de la orden
        if (!empty($comment)) {
            // asocia los valores retornados al medio de pago para los metodos de captura y cancelacion
            $this->p2pStatus = $status;
            $wasPaymentInformationChanged = $this->_importPaymentInformation($order, $p2p, $status);

            // si el estado es procesado, remite el email
            if ($state == Mage_Sales_Model_Order::STATE_PROCESSING) {
                // almacena el n�mero de autorizacion
                $order->getPayment()->setLastTransId($p2p->getAuthorization());
                $order->setState($state, $status, $comment)
                    ->save();

                if ($wasCancelled) {
                    /**
                     * Set the product ID
                     */
                    $EntityId = $order->getEntityId();

                    // Un-cancel the specified order and the items related
                    $order = Mage::getModel('sales/order')->load($EntityId);
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                    $order->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED);
                    $order->save();

                    foreach ($order->getAllItems() as $item) {
                        $item->setQtyCanceled(0);
                        $item->save();
                    }
                }

                // agrega la factura
                $this->_createInvoice($order);
                // envia el correo con la orden
                $order->sendNewOrderEmail()
                    ->setEmailSent(true)
                    ->save();

                $wasPaymentInformationChanged = true;
            } elseif ($state == Mage_Sales_Model_Order::STATE_CANCELED) {
                // establece el pago como declinado y cancela la orden
                $order
                    ->cancel()
                    ->addStatusToHistory($status, $comment)
                    ->save();
                $wasPaymentInformationChanged = true;
            } elseif ($state == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->getPayment()->setLastTransId($p2p->getAuthorization());
                // agrega un comentario a la historia
                $order
                    ->addStatusToHistory($status, $comment)
                    ->save();
                $wasPaymentInformationChanged = true;
            }
            if ($wasPaymentInformationChanged)
                $order->getPayment()->save();
        }

    }

    /**
     * TODO: DC
     * @param Mage_Sales_Model_Order $order
     * @param $reference
     * @return mixed
     */
    public function processPayment($order, $reference)
    {
        $p2p = new PlacetoPay();
        // Login y tranKey
        $p2p->setLogin($this->getConfigData('login'));
        $p2p->setTranKey($this->getConfigData('trankey'));

        $p2p->getPaymentResponse((int)$order->getBaseDiscountCanceled());
        // procesa el asiento de la orden acorde al resultado dado por PlacetoPay
        $this->settlePlacetoPayPayment($order, $p2p);

        return $order->getEntityId();
    }

    /**
     * Asocia la informaci�n del pago retornada por PlacetoPay al objeto de pago
     * Retorna true si hubo cambios en la informaci�n
     *
     * @param Mage_Sales_Model_Order $order
     * @param PlacetoPay $p2p
     * @param string $status
     * @return bool
     */
    protected function _importPaymentInformation(Mage_Sales_Model_Order $order, PlacetoPay $p2p, $status)
    {
        $payment = $order->getPayment();
        $was = $payment->getAdditionalInformation();
        $from = [
            EGM_PlacetoPay_Model_Info::RESPONSE_STATUS => $status,
            EGM_PlacetoPay_Model_Info::TRANSACTION_DATE => $p2p->response()->status->date,
            EGM_PlacetoPay_Model_Info::RESPONSE_CODE => $p2p->response()->status->reason,
            EGM_PlacetoPay_Model_Info::RESPONSE_MESSAGE => html_entity_decode($p2p->response()->status->message),
            EGM_PlacetoPay_Model_Info::REFERENCE => $p2p->response()->payment->reference,
        ];

        Mage::getSingleton('placetopay/info')->importToPayment($from, $payment);
        return $was != $payment->getAdditionalInformation();
    }

}
