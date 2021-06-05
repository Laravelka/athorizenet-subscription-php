<?php

require __DIR__.'/vendor/autoload.php';

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

const AUTHORIZENET_LOG_FILE = 'phplog';
const MERCHANT_LOGIN_ID = '3pY92EXXTa82';
const MERCHANT_TRANSACTION_KEY = '664Tg82X54s6KuJD';

date_default_timezone_set('Europe/Moscow');

class AuthorizeSubscription
{
	private $order = null;
	private $billTo = null;
	private $payment = null;
	private $request = null;
	private $creditCard = null;
	private $subscription = null;
	private $paymentSchedule = null;
	private $merchantAuthentication = null;

	public function __construct()
	{	
		$this->order = new AnetAPI\OrderType();
		$this->billTo = new AnetAPI\NameAndAddressType();
		$this->payment = new AnetAPI\PaymentType();
		$this->request = new AnetAPI\ARBCreateSubscriptionRequest();
		$this->interval = new AnetAPI\PaymentScheduleType\IntervalAType();
		$this->creditCard = new AnetAPI\CreditCardType();
		$this->subscription = new AnetAPI\ARBSubscriptionType();
		$this->paymentSchedule = new AnetAPI\PaymentScheduleType();
		$this->merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
	}

	public function authentication(string $merchantLoginId = null, string $merchantTransactionKey = null)
	{
		$this->merchantAuthentication->setName($merchantLoginId);
		$this->merchantAuthentication->setTransactionKey($merchantTransactionKey);
	}

	public function setType(string $name)
	{
		$this->subscription->setName($name);
	}

	public function setInterval(int $intervalLength = 7, string $unit = 'days')
	{	
		$this->interval->setLength($intervalLength);
		$this->interval->setUnit($unit);
	}

	public function setScheclude(string $startDate, string $totalOccurrences, string $trialOccurrences)
	{
		$this->paymentSchedule->setInterval($this->interval);
		$this->paymentSchedule->setStartDate(new DateTime($startDate));
		$this->paymentSchedule->setTotalOccurrences($totalOccurrences);
		$this->paymentSchedule->setTrialOccurrences($trialOccurrences);
	}

	public function setSubscription(float $amount, float $trialAmount = 0.00)
	{
		$this->subscription->setPaymentSchedule($this->paymentSchedule);
    	$this->subscription->setAmount($amount);
    	$this->subscription->setTrialAmount($trialAmount);
	}

	public function setCreditCard(float $number, string $expirationDate)
	{
    	$this->creditCard->setCardNumber($number);
   		$this->creditCard->setExpirationDate($expirationDate);

		$this->payment->setCreditCard($this->creditCard);
   		$this->subscription->setPayment($this->payment);
	}

	public function setOrder($invoiceNumber, string $description)
	{
		$this->order->setInvoiceNumber($invoiceNumber);        
		$this->order->setDescription($description);

		$this->subscription->setOrder($this->order);
	}

	public function setBillTo(string $firstName, string $lastName)
	{
		$this->billTo->setFirstName($firstName);
		$this->billTo->setLastName($lastName);

		$this->subscription->setBillTo($this->billTo);
	}

	public function createRequest(string $refId)
	{
		$this->request->setmerchantAuthentication($this->merchantAuthentication);
    	$this->request->setRefId($refId);
   		$this->request->setSubscription($this->subscription);

		$controller = new AnetController\ARBCreateSubscriptionController($this->request);

		$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
		
		/*
		Можно сделать такую проверку ответа и вернуть в зависимости от ответа id подписки или массив сообщений с ошибками
		if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
			return $response->getSubscriptionId();
		} else {
			return $response->getMessages()->getMessage();
		}
		*/

		return $response;
	}
}

$subscription = new AuthorizeSubscription();

$refId = 'ref_'.time(); // уникальный идентификатор, который вернется в ответе

// аутентификация
$subscription->authentication(MERCHANT_LOGIN_ID, MERCHANT_TRANSACTION_KEY);

// название подписки
$subscription->setType('Тестовая подписка');

// интервал
$subscription->setInterval(7, 'days');

/*
 * Дата первой подписки
 * Количество платежей за подписку (если указать 9999, то навсегда)
 * Количество платежей в пробный период.
*/
$subscription->setScheclude('2021-06-07', '10', '1');

// цена за подписку и цена за пробную подписку
$subscription->setSubscription(10.0, 0.00);

// номер карты и дата истечения срока годности
$subscription->setCreditCard(4111111111111111, '2038-12');

// Определяемый продавцом номер счета-фактуры, связанный с заказом и описание заказа
$subscription->setOrder('12345', 'Тестовое описание подписки');

// Имя и фамилия связанные с платежным адресом клиента.
$subscription->setBillTo('John', 'Doe');

// отправка запроса
$response = $subscription->createRequest($refId);

if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
	echo $response->getSubscriptionId();
} else {
	$errorMessages = $response->getMessages()->getMessage();
	echo 'Ошибка: '.$errorMessages[0]->getCode().' '.$errorMessages[0]->getText()."\n";
}
