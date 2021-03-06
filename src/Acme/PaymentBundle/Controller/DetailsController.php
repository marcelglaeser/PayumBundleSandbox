<?php
namespace Acme\PaymentBundle\Controller;

use Payum\Bundle\PayumBundle\Controller\PayumController;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Sync;
use Symfony\Component\HttpFoundation\Request;

class DetailsController extends PayumController
{
    public function viewAction(Request $request)
    {
        $token = $this->getHttpRequestVerifier()->verify($request);

        $gateway = $this->getPayum()->getGateway($token->getGatewayName());

        try {
            $gateway->execute(new Sync($token));
        } catch (RequestNotSupportedException $e) {}

        $gateway->execute($status = new GetHumanStatus($token));

        $refundToken = null;
        if ($status->isCaptured() || $status->isAuthorized()) {
            $refundToken = $this->getTokenFactory()->createRefundToken(
                $token->getGatewayName(),
                $status->getFirstModel(),
                $request->getUri()
            );
        }

        return $this->render('AcmePaymentBundle:Details:view.html.twig', array(
            'status' => $status->getValue(),
            'payment' => htmlspecialchars(json_encode(
                iterator_to_array($status->getFirstModel()),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )),
            'gatewayTitle' => ucwords(str_replace(array('_', '-'), ' ', $token->getGatewayName())),
            'refundToken' => $refundToken
        ));
    }

    public function viewOrderAction(Request $request)
    {
        $token = $this->getHttpRequestVerifier()->verify($request);

        $gateway = $this->getPayum()->getGateway($token->getGatewayName());

        try {
            $gateway->execute(new Sync($token));
        } catch (RequestNotSupportedException $e) {}

        $gateway->execute($status = new GetHumanStatus($token));

        /** @var PaymentInterface $payment */
        $payment = $status->getFirstModel();

        return $this->render('AcmePaymentBundle:Details:viewOrder.html.twig', array(
            'status' => $status->getValue(),
            'payment' => htmlspecialchars(json_encode(
                array(
                    'client' => array(
                        'id' => $payment->getClientId(),
                        'email' => $payment->getClientEmail(),
                    ),
                    'number' => $payment->getNumber(),
                    'description' => $payment->getCurrencyCode(),
                    'total_amount' => $payment->getTotalAmount(),
                    'currency_code' => $payment->getCurrencyCode(),
                    'currency_digits_after_decimal_point' => $payment->getCurrencyDigitsAfterDecimalPoint(),
                    'details' => $payment->getDetails(),
                ),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )),
            'gatewayTitle' => ucwords(str_replace(array('_', '-'), ' ', $token->getGatewayName()))
        ));
    }
}
