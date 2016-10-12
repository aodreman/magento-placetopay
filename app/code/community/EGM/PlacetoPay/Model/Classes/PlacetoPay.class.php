<?php

require_once (__DIR__ . '/Authentication.php');

/**
 * Clase para la definición de excepciones
 */
class PlacetoPayException extends Exception
{
}

/**
 * Clase para el procesamiento de pagos a traves de PlacetoPay.
 * @author    Enrique Garcia M. <ingenieria@egm.co>
 * @copyright (c) 2004-2014 EGM Ingenieria sin fronteras S.A.S.
 * @since     Miercoles, Febrero 25, 2004
 */
class PlacetoPay
{
    /**
     * Define la version de trama usada por el componente
     * @internal
     */
    const VERSION = '2.0.3';

    /**
     * URL completa del script a donde se remite para el pago por interfaz
     * @internal
     */
    const PAYMENT_URL = 'https://www.placetopay.com/payment.php';

    /**
     * URL completa del servicio Web encargado de dar respuesta a la consulta de transacciones
     * @internal
     */
    const PAYMENT_WS_URL = 'https://www.placetopay.com/webservice.php';

    /* Constantes con el resultado de una transaccion */

    /**
     * Indicador de transaccion fallida
     */
    const P2P_ERROR = 0;

    /**
     * Indicador de transaccion exitosa
     */
    const P2P_APPROVED = 1;

    /**
     * Indicador de transaccion declinada
     */
    const P2P_DECLINED = 2;

    /**
     * Indicador de transaccion pendiente
     */
    const P2P_PENDING = 3;

    /**
     * Indicador de transaccion duplicada (previamente aprobada)
     */
    const P2P_DUPLICATE = 4;

    /**
     * Indicador de transaccion pendiente validacion precobro
     */
    const P2P_PENDING_VALIDATE_PRECHARGE = 5;

    /**
     * Referencia para el pago
     * @access private
     * @var string
     */
    private $reference;

    /**
     * Moneda usada para el pago
     * @access private
     * @var string
     */
    private $currency;

    /**
     * Idioma usado para las plantillas
     * @access private
     * @var string
     */
    private $language;

    /**
     * Valor total a pagar, incluye impuestos
     * @access private
     * @var string
     */
    private $totalAmount;

    /**
     * Valor del impuesto incluido en el pago
     * @access private
     * @var string
     */
    private $taxAmount;

    /**
     * Valor base para la devolución del impuesto, este valor solo aplica para
     * impuestos del 10% y 16% en Colombia y no para todos los proveedores, en
     * ningun caso podrá superar sumado al impuesto el valor total a pagar, para
     * compras no gravadas el valor deberá ser cero
     * @access private
     * @var string
     */
    private $devolutionBaseAmount;

    /**
     * Valor del servicio cobrado por las agencias de viajes
     * @access private
     * @var double
     */
    private $serviceFeeAmount;

    /**
     * Impuesto de la tasa administrativa para las agencias de viajes
     * @access private
     * @var double
     */
    private $serviceFeeTax;

    /**
     * La base de devolución del impuesto de la tasa administrativa para las agencias de viajes
     * @access private
     * @var double
     */
    private $serviceFeeDevolutionBase;

    /**
     * El código de la agencia para el reconocimiento de la tasa administrativa
     * @access private
     * @var string
     */
    private $serviceFeeCode;

    /**
     * El código de la aerolínea para la compensación del tiquete
     * @access private
     * @var string
     */
    private $airlineCode;

    /**
     * Impuesto o tasa aeroportuaria
     * @access private
     * @var double
     */
    private $airportTax;

    /**
     * Datos adicionales dados por el comercio, no usados por la plataforma
     * @access private
     * @var string
     */
    private $extraData;

    /**
     * Datos adicionales de control para la transaccion, dados en la forma nombre, valor
     * @access private
     * @var array
     */
    private $additionalData;

    /**
     * Datos para la compensación del pago, cuando se distribuye en nombre de
     * terceros
     * @access private
     * @var string
     */
    private $compensation;

    /**
     * URL completa a la cual debe enviarse la respuesta del pago, en caso que
     * se desee sobreescribir la establecida en la plataforma
     * @access private
     * @var string
     */
    private $overrideReturn;

    /**
     * Indicador de si el pago es recurrente o no
     * @access private
     * @var boolean
     */
    private $isRecurrent;

    /**
     * Periodicidad del pago recurrente expresado en [Y = años, M = meses, D = Dias]
     * @access private
     * @var string
     */
    private $recurrentPeriodicity;

    /**
     * Intervalo de aplicación a la periodicidad
     * @access private
     * @var int
     */
    private $recurrentInterval;

    /**
     * Fecha máxima hasta la cual se aplica el pago recurrente, debe ser una fecha válida
     * o UNLIMITED, si se especifica un número de períodos la recurrencia se hará al menor
     * valor
     * @access private
     * @var string
     */
    private $recurrentDueDate;

    /**
     * Número máximo de períodos para el pago recurrente, si se especifica una fecha máxima
     * para el pago recurrente, la recurrencia se hará al menor valor entre ambos
     * @access private
     * @var int
     */
    private $recurrentMaxPeriods;

    /**
     * Franquicia elegida para el pago
     * @access private
     * @var string
     */
    private $franchise;

    /**
     * Nombre de la franquicia elegida para el pago
     * @access private
     * @var string
     */
    private $franchiseName;

    /**
     * Número de referencia que fue pasado a la entidad financiera
     * @access private
     * @var string
     */
    private $internalReference;

    /**
     * Número de autorización de la transacción dado por la entidad financiera
     * @access private
     * @var string
     */
    private $authorization;

    /**
     * Número de recibo o comprobante de la transacción dado por la entidad financiera
     * @access private
     * @var string
     */
    private $receipt;

    /**
     * Número de código único o comercio en la entidad financiera
     * @access private
     * @var string
     */
    private $retailCode;

    /**
     * Número de terminal o servicio en la entidad financiera
     * @access private
     * @var string
     */
    private $terminalNumber;

    /**
     * Fecha y hora de la transacción
     * @access private
     * @var string
     */
    private $transactionDate;

    /**
     * Número de tarjeta de crédito usada en la transacción
     * @access private
     * @var string
     */
    private $creditCardNumber;

    /**
     * Número de cuotas que fueron seleccionadas para diferir el pago
     * @access private
     * @var string
     */
    private $creditCardPeriod;

    /**
     * Tipo de cuenta usada
     * @access private
     * @var string
     */
    private $accountType;

    /**
     * Nombre del tipo de cuenta usada
     * @access private
     * @var string
     */
    private $accountTypeName;

    /**
     * Moneda con la cual fue realizado el pago acorde a la entidad financiera
     * @access private
     * @var string
     */
    private $bankCurrency;

    /**
     * Nombre del banco con el cual se realizó la transacción
     * @access private
     * @var string
     */
    private $bankName;

    /**
     * Valor real pagado en la moneda aceptada por la entidad financiera
     * @access private
     * @var string
     */
    private $bankTotalAmount;

    /**
     * Factor de conversión usado por la entidad financiera
     * @access private
     * @var string
     */
    private $bankConversionFactor;

    /**
     * Código interno del error retornado por la entidad financiera
     * @access private
     * @var string
     */
    private $errorCode;

    /**
     * Código interno del error retornado por la entidad financiera en Base24 para ser mostrado en el comprobante
     * @access private
     * @var string
     */
    private $errorCodeB24;

    /**
     * Mensaje detallado del error retornado por la entidad financiera
     * @access private
     * @var string
     */
    private $errorMessage;

    /**
     * Directorio en donde se halla el repositorio de llaves
     * @access private
     * @var string
     */
    private $gpgHomeDirectory;

    /**
     * Ubicación del archivo ejecutable del GnuPG
     * @access private
     * @var string
     */
    private $gpgProgramPath;

    /**
     * Código interno del error retornado por la entidad financiera para la Tasa Administrativa
     * @access private
     * @var string
     */
    private $errorCodeTA;

    /**
     * Mensaje detallado del error retornado por la entidad financiera para la Tasa Administrativa
     * @access private
     * @var string
     */
    private $errorMessageTA;

    /**
     * Número de autorización de la transacción dado por la entidad financiera para la Tasa Administrativa
     * @access private
     * @var string
     */
    private $authorizationTA;

    /**
     * Número de recibo o comprobante de la transacción dado por la entidad financiera para la Tasa Administrativa
     * @access private
     * @var string
     */
    private $receiptTA;

    // TODO: DC
    protected $login;
    protected $tranKey;
    protected $buyer = [];
    protected $payer = [];

    function __construct()
    {
        $this->currency = 'COP';
        $this->language = 'ES';
        $this->additionalData = [];
        $this->devolutionBaseAmount = '0';

        $this->serviceFeeAmount = 0;
        $this->serviceFeeTax = 0;
        $this->serviceFeeDevolutionBase = 0;
        $this->airportTax = 0;

        $this->isRecurrent = false;
        $this->recurrentPeriodicity = 'Y';
        $this->recurrentInterval = 1;
        $this->recurrentDueDate = 'UNLIMITED';
        $this->recurrentMaxPeriods = -1;
    }

    /**
     * Retorna la version del componente
     * @return string
     */
    function getVersion()
    {
        return 'PlacetoPay PHP Component ' . self::VERSION;
    }

    public function setReference($reference)
    {
        $this->reference = $reference;
        return $this;
    }

    public function setLogin($login)
    {
        $this->login = $login;
        return $this;
    }

    public function setTranKey($tranKey)
    {
        $this->tranKey = $tranKey;
        return $this;
    }

    public function setTotalAmount($totalAmount)
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function setTaxAmount($taxAmount)
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    /**
     * Obtiene el numero de referencia que origina la transaccion
     * @return string
     */
    function getReference()
    {
        return $this->reference;
    }

    /**
     * Obtiene la moneda usada para el pago
     * @return string
     */
    function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Establece la moneda original para el pago
     * @param string $currency
     */
    function setCurrency($currency)
    {
        $this->currency = strtoupper($currency);
    }

    /**
     * Establece el idioma para la plataforma
     * @param string $language
     */
    function setLanguage($language)
    {
        $this->language = strtoupper($language);
    }

    /**
     * Obtiene el idioma usado para la operacion
     * @return string
     */
    function getLanguage()
    {
        return $this->language;
    }

    /**
     * Obtiene el total de la compra incluido el IVA
     * @return string
     */
    function getTotalAmount()
    {
        return $this->totalAmount;
    }

    /**
     * Obtiene el IVA de la compra
     * @return string
     */
    function getTaxAmount()
    {
        return $this->taxAmount;
    }

    /**
     * Obtiene el impuesto o tasa aeroportuaria
     * @return string
     */
    function getAirportTax()
    {
        return number_format($this->airportTax, 2, '.', '');
    }

    /**
     * Obtiene el valor del servicio cobrado por las agencias de viajes
     * @return string
     */
    function getServiceFee()
    {
        return number_format($this->serviceFeeAmount, 2, '.', '');
    }

    /**
     * Obtiene la moneda usada por la entidad financiera para procesar la transacción
     * @return string
     */
    function getPlatformCurrency()
    {
        return $this->bankCurrency;
    }

    /**
     * Obtiene el total de la compra incluido el IVA acorde a la moneda usada por la entidad financiera
     * @return string
     */
    function getPlatformTotalAmount()
    {
        return $this->bankTotalAmount;
    }

    /**
     * Obtiene el factor de conversión usado por la entidad financiera
     * @return string
     */
    function getPlatformConversionFactor()
    {
        return $this->bankConversionFactor;
    }

    /**
     * Retorna el nombre del comprador/pagador
     * @return string
     */
    function getShopperName()
    {
        return isset($this->payer['name']) ? $this->payer['name'] : null;
    }

    /**
     * Retorna el correo electronico del comprador/pagador
     * @return string
     */
    function getShopperEmail()
    {
        return isset($this->payer['email']) ? $this->payer['email'] : null;
    }

    function setPayerInfo($documentType, $document, $name, $surname, $email, $address = '', $city = '', $province = '', $country = '', $phone = '', $mobile = '')
    {
        $this->payer = $this->parsePerson($documentType, $document, $name, $surname, $email, $address, $city, $province, $country, $phone, $mobile);
        return $this;
    }

    function setBuyerInfo($documentType, $document, $name, $surname, $email, $address = null, $city = null, $province = null, $country = null, $phone = null, $mobile = null)
    {
        $this->buyer = $this->parsePerson($documentType, $document, $name, $surname, $email, $address, $city, $province, $country, $phone, $mobile);
        return $this;
    }

    public function parsePerson($documentType, $document, $name, $surname, $email, $address = null, $city = null, $province = null, $country = null, $phone = null, $mobile = null)
    {
        if (!empty($documentType) && !in_array($documentType, array('CC', 'CE', 'TI', 'PPN', 'NIT', 'SSN', 'LIC', 'TAX', 'RC')))
            throw new PlacetoPayException('El tipo de documento del comprador no es soportado');
        if (!empty($document) && (strlen($document) > 12))
            throw new PlacetoPayException('El número de documento no puede exceder los 12 caracteres');
        if (!empty($country) && (strlen($country) > 2))
            throw new PlacetoPayException('El código del país no puede exceder los 2 caracteres acorde a la codificación ISO 3166-1');

        $person = [
            'name' => (empty($name) ? null : trim($name)),
            'surname' => (empty($surname) ? null : trim($surname)),
            'email' => (empty($email) ? null : trim($email)),
            'documentType' => (empty($documentType) ? null : $documentType),
            'document' => (empty($document) ? null : $document),
            'mobile' => (empty($mobile) ? null : trim($mobile)),
            'address' => [
                'street' => (empty($address) ? null : trim($address)),
                'city' => (empty($city) ? null : trim($city)),
                'state' => (empty($province) ? null : trim($province)),
                'country' => (empty($country) ? null : strtoupper(trim($country))),
                'phone' => (empty($phone) ? null : trim($phone)),
            ]
        ];

        return array_filter($person);
    }

    function addAdditionalData($keyword, $value)
    {
        if (empty($keyword) || (strlen($keyword) > 30))
            throw new PlacetoPayException('El nombre de la variable para el dato adicional no puede superar 30 caracteres');
        $this->additionalData[(string)$keyword] = $value;
    }

    /**
     * Define si el pago es recurrente o no
     * @param string $periodicity use los valores D - diario, M - mensual, Y - anual
     * @param int $interval
     * @param int $periods
     * @param string $dueDate
     */
    function setRecurrent($periodicity, $interval, $periods, $dueDate)
    {
        $periodicity = strtoupper($periodicity);
        $dueDate = strtoupper($dueDate);
        $interval = intval($interval);
        $periods = intval($periods);
        if (($periodicity != 'D') && ($periodicity != 'M') && ($periodicity != 'Y'))
            throw new PlacetoPayException('La periodicidad soportada es D[diaria], M[mensual], Y[anual]');
        if (($interval < 1) || ($interval > 99))
            throw new PlacetoPayException('El intervalo para la periodicidad soportada está fuera de rango');
        if (($periods < -1) || ($periods == 0))
            throw new PlacetoPayException('El número de iteraciones del pago debe ser -1 para ilimitado o un número superior a cero');
        if ($dueDate != 'UNLIMITED') {
            $dueDate = @strtotime($dueDate);
            if (($dueDate == -1) || ($dueDate === false))
                throw new PlacetoPayException('La fecha máxima para la recurrencia no pudo ser establecida, use un formato yyyy-mm-dd');
            $dueDate = date('Y-m-d', $dueDate);
        }

        $this->isRecurrent = true;
        $this->recurrentPeriodicity = $periodicity;
        $this->recurrentInterval = $interval;
        $this->recurrentMaxPeriods = $periods;
        $this->recurrentDueDate = $dueDate;
    }

    /**
     * Retorna los datos adicionales
     * @return string
     */
    function getExtraData()
    {
        return $this->extraData;
    }

    /**
     * Establece la informacion adicional
     * @param string $extra
     */
    function setExtraData($extra)
    {
        $this->extraData = (empty($extra) ? '' : $extra);
    }

    /**
     * Establece el codigo de compensacion, solo valido con VBV
     * @param string $compensation
     */
    function setCompensation($compensation)
    {
        $this->compensation = (empty($compensation) ? '' : $compensation);
    }

    /**
     * Establece la tasa administrativa para las agencias de viajes
     * @param double $amount
     * @param double $tax
     * @param double $devolutionBase
     * @param string $code
     */
    function setServiceFee($amount, $tax = 0, $devolutionBase = 0, $code = '')
    {
        if (!is_numeric($amount) || $amount < 0)
            throw new PlacetoPayException('El valor de la tasa administrativa debe ser un valor numérico');
        if (!is_numeric($tax) || $tax < 0 || $tax > $amount)
            throw new PlacetoPayException('El valor del impuesto asociado a la tasa administrativa debe ser un valor numérico');
        if (!is_numeric($devolutionBase) || $devolutionBase < 0 || $devolutionBase > ($amount - $tax) || ($devolutionBase > 0 && $tax == 0))
            throw new PlacetoPayException('El valor de la base de devolucion del impuesto asociado a la tasa administrativa debe ser un valor numérico no mayor al valor de la tasa y ser cero en caso de que no haya impuesto');

        $this->serviceFeeAmount = $amount;
        $this->serviceFeeTax = $tax;
        $this->serviceFeeDevolutionBase = $devolutionBase;
        $this->serviceFeeCode = $code;
    }

    /**
     * Establece el código de la aerolinea, solo válido si se especifica la tasa administrativa
     * @param string $code
     */
    function setAirlineCode($code)
    {
        $this->airlineCode = $code;
    }

    /**
     * Establece el valor de la tasa aeroportuaria, solo válido si se especifica la tasa administrativa
     * @param double $amount
     */
    function setAirportTax($amount)
    {
        if (!is_numeric($amount) || $amount < 0)
            throw new PlacetoPayException('El valor de la tasa aeroportuaria debe ser un valor numérico');
        $this->airportTax = $amount;
    }

    /**
     * Establece la ruta a donde debe enviar la trama de respuesta
     * @param string $returnURL
     */
    function setOverrideReturn($returnURL)
    {
        $this->overrideReturn = (empty($returnURL) ? '' : $returnURL);
    }

    /**
     * Establece la franquicia predeterminada
     * @param string
     */
    function setFranchise($franchise)
    {
        if (!in_array($franchise, array('CR_VS', 'CR_AM', 'CR_DN', 'CR_CR', '_PSE_', 'RM_MC', 'V_VBV', 'TY_EX', 'TY_AK', 'SFPAY', 'PINVL')))
            throw new PlacetoPayException('Se espera un código de franquicia válido');
        $this->franchise = $franchise;
    }

    /**
     * Retorna la franquicia con la cual se realizo la transaccion
     * @return string
     */
    function getFranchise()
    {
        return $this->franchise;
    }

    /**
     * Retorna el nombre de la franquicia con la cual se realizo la transaccion
     * @return string
     */
    function getFranchiseName()
    {
        return $this->franchiseName;
    }

    /**
     * Retorna el banco con el cual se hizo la transaccion
     * @return string
     */
    function getBankName()
    {
        return $this->bankName;
    }

    /**
     * Retorna el numero de referencia interna con que fue solicitado a la entidad financiera
     * @return string
     */
    function getInternalReference()
    {
        return $this->internalReference;
    }

    /**
     * Retorna el numero de autorizacion de la transaccion
     * @return string
     */
    function getAuthorization()
    {
        return $this->authorization;
    }

    /**
     * Retorna el numero de recibo de la transaccion
     * @return string
     */
    function getReceipt()
    {
        return $this->receipt;
    }

    /**
     * Retorna el numero de codigo unico o establecimiento
     * @return string
     */
    function getRetailCode()
    {
        return $this->retailCode;
    }

    /**
     * Retorna el numero de terminal o servicio
     * @return string
     */
    function getTerminalNumber()
    {
        return $this->terminalNumber;
    }

    /**
     * Retorna la fecha y hora de la transaccion
     * @return string
     */
    function getTransactionDate()
    {
        return $this->transactionDate;
    }

    /**
     * Retorna el numero de la tarjeta de credito con la cual se hizo la transaccion
     * @return string
     */
    function getCreditCardNumber()
    {
        return $this->creditCardNumber;
    }

    /**
     * Retorna el numero cuotas con que fue diferida la tarjeta
     * @return string
     */
    function getCreditCardPeriod()
    {
        return $this->creditCardPeriod;
    }

    /**
     * Retorna el tipo de cuenta de la tarjeta
     * @return string
     */
    function getAccountType()
    {
        return $this->accountType;
    }

    /**
     * Retorna el nombre del tipo de cuenta de la tarjeta
     * @return string
     */
    function getAccountTypeName()
    {
        return $this->accountTypeName;
    }

    /**
     * Retorna el codigo de error de la transaccion
     * @return string
     */
    function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Retorna el codigo de error de la transaccion a ser mostrado
     * @return string
     */
    function getErrorCodeB24()
    {
        return $this->errorCodeB24;
    }

    /**
     * Retorna el mensaje de error de la transaccion
     * @return string
     */
    function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Retorna el codigo de error de la transaccion de Tasa Administrativa
     * @return string
     */
    function getErrorCodeTA()
    {
        return $this->errorCodeTA;
    }

    /**
     * Retorna el mensaje de error de la transaccion de Tasa Administrativa
     * @return string
     */
    function getErrorMessageTA()
    {
        return $this->errorMessageTA;
    }

    /**
     * Retorna el numero de autorizacion de la transaccion de Tasa Administrativa
     * @return string
     */
    function getAuthorizationTA()
    {
        return $this->authorizationTA;
    }

    /**
     * Retorna el numero de recibo de la transaccion de Tasa Administrativa
     * @return string
     */
    function getReceiptTA()
    {
        return $this->receiptTA;
    }

    /**
     * Busca un valor como si el dato viniera de una entidad XML
     *
     * @param string $entity
     * @param string $context
     * @return string
     */
    private function getEntityValue($entity, $context)
    {
        $matcher = false;
        if (preg_match('/<' . $entity . '\\s.*>(.*)<\/' . $entity . '>/s', $context, $matcher))
            return $matcher[1];
        return null;
    }

    public function serviceResponseCode()
    {
        return ($this->serviceResponse) ? $this->serviceResponse->requestId : null;
    }

    /**
     * Retorna los campos ocultos para un formulario
     *
     * @param string $keyID
     * @param string $passPhrase
     * @param string $recipientKeyID
     * @param string $customerSiteID
     * @param string $reference
     * @param double $totalAmount
     * @param double $taxAmount
     * @param double $devolutionBaseAmount
     * @return string
     */
    function getPaymentHiddenFields(
        $keyID, $passPhrase, $recipientKeyID,
        $customerSiteID, $reference, $totalAmount,
        $taxAmount, $devolutionBaseAmount = 0)
    {
        $paymentData = $this->getPaymentRequest($keyID, $passPhrase,
            $recipientKeyID, $customerSiteID, $reference, $totalAmount,
            $taxAmount, $devolutionBaseAmount, $this->franchise);

        if (!empty($paymentData)) {
            $paymentData = '<input type="hidden" name="CustomerSiteID" value="' . htmlspecialchars($customerSiteID)
                . '" /><input type="hidden" name="PaymentRequest" value="' . htmlspecialchars($paymentData)
                . '" /><input type="hidden" name="Language" value="' . htmlspecialchars($this->language)
                . '" />';
        }

        return $paymentData;
    }

    /**
     * Retorna un formulario HTML con el boton para el envio de la trama
     *
     * @param string $keyID
     * @param string $passPhrase
     * @param string $recipientKeyID
     * @param string $customerSiteID
     * @param string $reference
     * @param double $totalAmount
     * @param double $taxAmount
     * @param double $devolutionBaseAmount
     * @return string
     */
    function getPaymentButton(
        $keyID, $passPhrase, $recipientKeyID,
        $customerSiteID, $reference, $totalAmount,
        $taxAmount, $devolutionBaseAmount = 0)
    {
        $paymentData = $this->getPaymentHiddenFields($keyID, $passPhrase,
            $recipientKeyID, $customerSiteID, $reference, $totalAmount,
            $taxAmount, $devolutionBaseAmount);
        if (!empty($paymentData))
            $paymentData = '<form id="frmEGM_P2P" method="post" action="' . self::PAYMENT_URL . '">' .
                $paymentData .
                '<input type="submit" name="btnEGMConfirm" value="Pagar con PlacetoPay"/>' .
                '</form>';

        return $paymentData;
    }

    public function getWSDLClient()
    {
        $wsdl = 'http://redirection.p2p.dev/soap/redirect?wsdl';
        $config = [
            'authentication' => SOAP_AUTHENTICATION_BASIC,
            'soap_version' => SOAP_1_2,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'trace' => false,
            'encoding' => 'UTF-8',
            'location' => 'http://redirection.p2p.dev/soap/redirect'
        ];

        $client = new SoapClient($wsdl, $config);
        $auth = new Authentication(['login' => $this->login, 'tranKey' => $this->tranKey]);
        $client->__setSoapHeaders($auth->getSoapHeader());
        return $client;
    }

    /**
     * Retorna la URL con el posteo de la información para el pago
     * @return string
     */
    function getPaymentRedirect()
    {
        $paymentData = $this->getPaymentRequest();

        try {
            $client = $this->getWSDLClient();
            $response = $client->createRequest(['payload' => $paymentData])->createRequestResult;
            if($response->status->status == 'OK'){
                $this->serviceResponse = $response;
                return $response->processUrl;
            }else{
                $this->errorCode = $response->status->reason;
                $this->errorMessage = $response->status->message;
            }

        } catch (Exception $e) {
            $this->errorCode = $e->getCode();
            $this->errorMessage = $e->getMessage();
            var_dump($e->getMessage());
            die();
        }
    }

    /**
     * Models the RedirectRequest as an array
     * @return array
     */
    public function getPaymentRequest()
    {
        // TODO: DC Configuration for allowPartial
        $redirectRequest = [
            'locale' => 'es_CO',
            'buyer' => $this->payer,
            'payment' => [
                'reference' => $this->reference,
                'description' => utf8_decode($this->extraData),
                'amount' => [
                    'currency' => $this->currency,
                    'total' => $this->totalAmount
                ],
                'allowPartial' => false,
            ],
            'expiration' => date('c', strtotime("+2 days")),
            'ipAddress' => Mage::helper('core/http')->getRemoteAddr(),
            'returnUrl' => $this->overrideReturn,
            'userAgent' => Mage::helper('core/http')->getHttpUserAgent()
        ];

        return $redirectRequest;
    }

    public function response()
    {
        return $this->result;
    }

    public function responseCode()
    {
        $result = $this->result;
        if(!$result)
            return self::P2P_PENDING;

        switch ($result->status->status) {
            case 'APPROVED':
                $ret = self::P2P_APPROVED;
                break;
            case 'PENDING':
                $ret = self::P2P_PENDING;
                break;
            case 'REJECTED':
                $ret = self::P2P_DECLINED;
                break;
            case 'FAILED':
                $ret = self::P2P_ERROR;
                break;
        }

        return $ret;
    }

    function getPaymentResponse($requestId)
    {
        try {
            $client = $this->getWSDLClient();
            $result = $client->getRequestInformation(['requestId' => $requestId])->getRequestInformationResult;

            $this->result = $result;

            switch ($this->result->status->status) {
                case 'APPROVED':
                    $ret = self::P2P_APPROVED;
                    break;
                case 'PENDING':
                    $ret = self::P2P_PENDING;
                    break;
                case 'FAILED':
                    $ret = self::P2P_ERROR;
                    break;
                case 'PENDING_VALIDATION':
                    $ret = self::P2P_PENDING_VALIDATE_PRECHARGE;
                    break;
                default:
                    $ret = ((substr($this->errorCode, 0, 1) == 'X') ? self::P2P_ERROR : self::P2P_DECLINED);
                    break;
            }

        } catch (Exception $e) {
            var_dump($e->getMessage());
            die();
        }
//        // respuesta predeterminada de la funcion
//        $ret = self::P2P_ERROR;
//
//        // instancia el objeto de GnuPG
//        $gpg = new egmGnuPG($this->gpgProgramPath, $this->gpgHomeDirectory);
//        $paymentResponse = $gpg->Decrypt($keyID, $passPhrase, $paymentResponse);
//        if (($paymentResponse == false) || ($paymentResponse == '')) {
//            $this->errorCode = 'GPG';
//            $this->errorMessage = $gpg->error;
//        } else {
//            $delim = chr(1);
//
//            // obtiene los valores de la respuesta, los cuales vienen
//            // posicionales asi:
//            // SIEMPRE:
//            // CustomerSiteID, Reference, Currency, TotalAmount, TaxAmount,
//            // bankCurrency, bankTotalAmount, TaxAmountCNV,
//            // payerName, payerEmail, ExtraData,
//            // ErrorCode, ErrorMessage
//            // EXITOSA:
//            // Franchise, FranchiseName, Authorization, Receipt, Date,
//            // CreditCard*, BankName*
//            $data = explode($delim, $paymentResponse);
//
//            // obtiene los basicos
//            if (count($data) >= 13) {
//                $this->reference = $data[1];
//                $this->currency = $data[2];
//                $this->totalAmount = $data[3];
//                $this->taxAmount = $data[4];
//                $this->bankCurrency = $data[5];
//                $this->bankTotalAmount = $data[6];
//                if ($this->totalAmount == $this->bankTotalAmount)
//                    $this->bankConversionFactor = '1.00';
//                elseif ($this->bankTotalAmount == '' || $this->bankTotalAmount == '0.00')
//                    $this->bankConversionFactor = '0.00';
//                else
//                    $this->bankConversionFactor = number_format(floatval($this->bankTotalAmount) / floatval($this->totalAmount), 2, '.', '');
//                $this->payerName = utf8_encode($data[8]);
//                $this->payerEmail = $data[9];
//                $this->extraData = utf8_encode($data[10]);
//                $this->errorCode = $data[11];
//                $this->errorMessage = utf8_encode($data[12]);
//
//                // carga las opcionales
//                $this->franchise = (isset($data[13]) ? $data[13] : "");
//                $this->franchiseName = (isset($data[14]) ? utf8_encode($data[14]) : "");
//                $this->authorization = (isset($data[15]) ? $data[15] : "");
//                $this->receipt = (isset($data[16]) ? $data[16] : "");
//                $this->transactionDate = (isset($data[17]) ? $data[17] : "");
//                $this->creditCardNumber = (isset($data[18]) ? $data[18] : "");
//                $this->bankName = (isset($data[19]) ? utf8_encode($data[19]) : "");
//                $this->errorCodeTA = (isset($data[20]) ? $data[20] : '');
//                $this->errorMessageTA = (isset($data[21]) ? utf8_encode($data[21]) : '');
//                $this->authorizationTA = (isset($data[22]) ? $data[22] : '');
//                $this->receiptTA = (isset($data[23]) ? $data[23] : '');
//                $this->airportTax = (isset($data[24]) ? floatval($data[24]) : 0.00);
//                $this->serviceFeeAmount = (isset($data[25]) ? floatval($data[25]) : 0.00);
//                $this->internalReference = (isset($data[26]) ? $data[26] : '');
//                $this->retailCode = (isset($data[27]) ? $data[27] : '');
//                $this->terminalNumber = (isset($data[28]) ? $data[28] : '');
//                $this->accountType = (isset($data[29]) ? $data[29] : '');
//                $this->accountTypeName = (isset($data[30]) ? $data[30] : '');
//                $this->creditCardPeriod = (isset($data[31]) ? $data[31] : '');
//                $this->errorCodeB24 = (isset($data[32]) ? $data[32] : $this->errorCode);
//
//                // determina la respuesta adecuada
//                switch ($this->errorCode) {
//                    case '00':
//                        $ret = self::P2P_APPROVED;
//                        break;
//                    case '09':
//                        $ret = self::P2P_DUPLICATE;
//                        break;
//                    case '?-':
//                        $ret = self::P2P_PENDING;
//                        break;
//                    case '?5':
//                        $ret = self::P2P_ERROR;
//                        break;
//                    case '?P':
//                        $ret = self::P2P_PENDING_VALIDATE_PRECHARGE;
//                        break;
//                    default:
//                        $ret = ((substr($this->errorCode, 0, 1) == 'X') ? self::P2P_ERROR : self::P2P_DECLINED);
//                        break;
//                }
//            } else {
//                $this->errorCode = 'P2P';
//                $this->errorMessage = 'Trama invalida, se espera más información.';
//            }
//        }
        return $ret;
    }

    /**
     * Consulta contra el Webservice si un pago fue exitoso o no.
     *
     * @param string $customerSiteID
     * @param string $reference
     * @param string $currency
     * @param double $amount
     * @param string $proxyType
     * @param string $proxyHost
     * @param string $proxyPort
     * @return int
     */
    function queryPayment($customerSiteID, $reference, $currency, $amount, $proxyType = 'DIRECT', $proxyHost = '', $proxyPort = 0)
    {
        if (!function_exists('curl_init')) {
            $this->errorCode = 'HTTP';
            $this->errorMessage = 'No hay soporte de cURL para realizar la conexion con el Webservice de PlacetoPay';
            return self::P2P_ERROR;
        }

        $soapText =
            '<?xml version="1.0" encoding="ISO-8859-1"?>' .
            '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="uri:PLACETOPAY" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:ns2="urn:PLACETOPAY" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">' .
            '<SOAP-ENV:Body>' .
            '<ns1:queryTransaction>' .
            '<request xsi:type="ns2:transactionInfoRequest">' .
            '<siteID xsi:type="xsd:string">' . $customerSiteID . '</siteID>' .
            '<reference xsi:type="xsd:string">' . urlencode($reference) . '</reference>' .
            '<currency xsi:type="xsd:string">' . $currency . '</currency>' .
            '<totalAmount xsi:type="xsd:decimal">' . number_format($amount, 2, '.', '') . '</totalAmount>' .
            '</request>' .
            '</ns1:queryTransaction>' .
            '</SOAP-ENV:Body>' .
            '</SOAP-ENV:Envelope>';

        // si hay un proxy de por medio, haga la conexion con el proxy

        // establece la conexion con el Webservice para consulta de transacciones
        $uc = curl_init();
        curl_setopt($uc, CURLOPT_URL, self::PAYMENT_WS_URL);
        curl_setopt($uc, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($uc, CURLOPT_TIMEOUT, 60);
        curl_setopt($uc, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($uc, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($uc, CURLOPT_POST, 1);
        curl_setopt($uc, CURLOPT_HTTPHEADER, array(
            'Content-type: text/xml; charset=ISO-8859-1"',
            'Accept: text/xml',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'SOAPAction: uri:PLACETOPAY/queryTransaction'
        ));
        curl_setopt($uc, CURLOPT_POSTFIELDS, $soapText);

        // obtiene la respuesta de la solicitud
        $soapText = utf8_encode(curl_exec($uc));
        $this->errorMessage = curl_error($uc);
        curl_close($uc);

        // verifica si hubo algun problema de conexion
        if (empty($soapText)) {
            $this->errorCode = 'HTTP';
            $this->errorMessage = 'La conexion con el servicio de pagos no pudo ser llevada a cabo en su totalidad ['
                . $this->errorMessage . ']';
            return self::P2P_ERROR;
        } // verifica si hay un SoapFault con lo que simplemente la transaccion no existe
        elseif ($this->getEntityValue('faultcode', $soapText)) {
            $this->errorCode = $this->getEntityValue("faultcode", $soapText);
            $this->errorMessage = $this->getEntityValue("faultstring", $soapText);
            return self::P2P_ERROR;
        } else {
            // llena el objeto con los valores retornados por el componente
            $this->reference = $reference;
            $this->totalAmount = number_format($amount, 2, '.', '');
            $this->payerName = $this->getEntityValue('shopperName', $soapText);
            $this->payerEmail = $this->getEntityValue('shopperEmail', $soapText);
            $this->franchise = $this->getEntityValue('franchise', $soapText);
            $this->franchiseName = $this->getEntityValue('franchiseName', $soapText);
            $this->bankName = $this->getEntityValue('bankName', $soapText);
            $this->bankCurrency = $this->getEntityValue('bankCurrency', $soapText);
            $this->bankTotalAmount = $this->getEntityValue('bankTotalAmount', $soapText);
            $this->creditCardNumber = $this->getEntityValue('creditCardNumber', $soapText);
            $this->transactionDate = $this->getEntityValue('transactionDate', $soapText);
            $this->errorCode = $this->getEntityValue('errorCode', $soapText);
            $this->errorMessage = $this->getEntityValue('errorMessage', $soapText);
            $this->authorization = $this->getEntityValue('authorization', $soapText);
            $this->receipt = $this->getEntityValue('receipt', $soapText);
            $this->errorCodeTA = $this->getEntityValue('errorCodeTA', $soapText);
            $this->errorMessageTA = $this->getEntityValue('errorMessageTA', $soapText);
            $this->authorizationTA = $this->getEntityValue('authorizationTA', $soapText);
            $this->receiptTA = $this->getEntityValue('receiptTA', $soapText);
            $this->extraData = $this->getEntityValue('extraData', $soapText);

            if ($this->totalAmount == $this->bankTotalAmount)
                $this->bankConversionFactor = '1.00';
            elseif ($this->bankTotalAmount == '' || $this->bankTotalAmount == '0.00')
                $this->bankConversionFactor = '0.00';
            else
                $this->bankConversionFactor = number_format(floatval($this->bankTotalAmount) / $amount, 2, '.', '');

            //'resultTA' => array('name' => 'resultTA', 'type' => 'xsd:int'),
            return (int)$this->getEntityValue('result', $soapText);
        }
    }
}
