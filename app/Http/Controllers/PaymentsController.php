<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Mail\PaymentReceived;
use App\Order;
use App\PaymentServiceOrders;
use App\PayPal;
use App\ThirdPartyPaymentService;
use App\Token;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PaymentsController extends Controller {

    public function listenPayPal(Request $request)
    {
        (new PayPal)->listen($request);
    }

    public function listenAllPay(Request $request)
    {
        if (PaymentServiceOrders::checkIfCheckMacValueCorrect($request) && PaymentServiceOrders::checkIfPaymentPaid($request->RtnCode))
        {
            $paymentServiceOrder = (new PaymentServiceOrders)->where('MerchantTradeNo', $request->MerchantTradeNo)->first();
            $paymentServiceOrder->update(['status' => 1, 'expiry_time' => null]);

            $orderRelations = $paymentServiceOrder->where('MerchantTradeNo', $request->MerchantTradeNo)->first()->orderRelations->where('payment_service_id', 1);
            Order::updateStatus($orderRelations);

            $user_id = $paymentServiceOrder->user->id;
            $payerEmail = Helpers::getFacebookResources(Token::getLatestToken($user_id))->getEmail();

            if ($payerEmail !== null)
            Mail::to($payerEmail)->send(new PaymentReceived($paymentServiceOrder, $orderRelations));

            return '1|OK';
        }
    }

    public function pay(Request $request, ThirdPartyPaymentService $thirdPartyPaymentService)
    {
//        $toBeValidatedCondition = [
//            'order_id' => 'required|array',
//        ];
//        $failMessage = Helpers::validation($toBeValidatedCondition, $request);
//        if ($failMessage)
//            return Helpers::result(false, $failMessage, 400);
//
//        if (!Helpers::checkIfIDExists($request, new Order(), 'order_id'))
//            return Helpers::result(false, 'The orders doesn\'t exist', 400);
//
//        if (!Helpers::checkIfBelongToTheUser($request, new Order(), 'order_id'))
//            return Helpers::result(false, 'The order doesn\'t belong to this user', 400);


        $orders = Order::whereIn('id', $request->order_id)->get();

//        if (Order::checkIfOrderPaid($orders))
//            return Helpers::result(false, 'The order has already been paid', 400);
//
//        if (Order::checkIfOrderExpired($orders))
//            return Helpers::result(false, 'The order has expired', 400);

        $toBeSavedInfo = [
            'total_amount' => Order::getTotalAmountForPayments($orders),
            'orders_name' => Order::getOrdersNameForPayments($orders),
            'merchant_trade_no' => time() . Helpers::createAUniqueNumber(),
            'merchant_trade_date' => date('Y/m/d H:i:s'),
            'trade_desc' => 'BuyBuyGo',
            'quantity' => 1,
            'user_id' => 1,
            'payment_service' => $thirdPartyPaymentService,
            'expiry_time' => (new Carbon())->now()->addDay(1)->toDateTimeString(),
            'orders' => $orders,
            'mc_currency' => 'TWD'
        ];

        switch ($thirdPartyPaymentService->id)
        {
            case 1:
                $error = (new PaymentServiceOrders)->make($toBeSavedInfo, $request);
                if($error)
                    return Helpers::result(false, $error,400);

                return (new PaymentServiceOrders())->send($toBeSavedInfo, $request);
                break;

            case 2:
                $error = (new PayPal)->make($toBeSavedInfo, $request);
                if($error)
                    return Helpers::result(false, $error, 400);

                $error = (new PayPal)->send($toBeSavedInfo, $request);
                if($error)
                    return Helpers::result(false, $error, 400);
        }

    }

    public function getPaymentService()
    {
        return Helpers::result(true, ThirdPartyPaymentService::all(), 200);
    }
}
