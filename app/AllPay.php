<?php

namespace App;

use AllInOne;
use App\Jobs\SendMailWhenPaymentPaid;
use App\Mail\PaymentReceived;
use Carbon\Carbon;
use CheckMacValue;
use EncryptType;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PaymentMethod;

class AllPay extends Model {

    protected $fillable = ['status', 'expiry_time', 'to_be_completed_date', 'TradeNo'];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function orderRelations()
    {
        return $this->hasMany('App\OrderRelations', 'payment_service_order_id', 'id');
    }

    public function recipient()
    {
        return $this->hasOne('App\Recipient', 'id', 'recipient_id');
    }

    public function send(Array $toBeSavedInfo, Request $request)
    {
        //載入SDK(路徑可依系統規劃自行調整)
        try
        {
            $obj = new AllInOne();

            //服務參數
            $obj->ServiceURL = "https://payment-stage.opay.tw/Cashier/AioCheckOut/V5";         //服務位置
            $obj->HashKey = env('HASHKEY');                                            //測試用Hashkey，請自行帶入AllPay提供的HashKey
            $obj->HashIV = env('HASHIV');                                            //測試用HashIV，請自行帶入AllPay提供的HashIV
            $obj->MerchantID = env('MERCHANTID');                                                      //測試用MerchantID，請自行帶入AllPay提供的MerchantID
            $obj->EncryptType = EncryptType::ENC_SHA256;                                        //CheckMacValue加密類型，請固定填入1，使用SHA256加密

            //基本參數(請依系統規劃自行調整)


            $obj->Send['ReturnURL'] = env('ALLPAY_RETURN_URL');
            $obj->Send['ClientBackURL'] = $request->ClintBackURL;
            $obj->Send['MerchantTradeNo'] = $toBeSavedInfo['merchant_trade_no'];                                 //訂單編號
            $obj->Send['MerchantTradeDate'] = $toBeSavedInfo['merchant_trade_date'];                              //交易時間
            $obj->Send['TotalAmount'] = $toBeSavedInfo['total_amount'];                                             //交易金額
            $obj->Send['TradeDesc'] = $toBeSavedInfo['trade_desc'];                                  //交易描述
            $obj->Send['ChoosePayment'] = PaymentMethod::ALL;                           //付款方式:Credit

            //訂單的商品資料
            foreach ($toBeSavedInfo['orders'] as $order)
            {
                array_push($obj->Send['Items'], array('Name'     => $order->item_name,
                                                      'Price'    => (int) $order->unit_price,
                                                      'Currency' => "元",
                                                      'Quantity' => (int) $order->quantity,
                                                      'URL'      => "dedwed"));
            }

            # 電子發票參數
            /*
            $obj->Send['InvoiceMark'] = InvoiceState::Yes;
            $obj->SendExtend['RelateNumber'] = $MerchantTradeNo;
            $obj->SendExtend['CustomerEmail'] = 'test@opay.tw';
            $obj->SendExtend['CustomerPhone'] = '0911222333';
            $obj->SendExtend['TaxType'] = TaxType::Dutiable;
            $obj->SendExtend['CustomerAddr'] = '台北市南港區三重路19-2號5樓D棟';
            $obj->SendExtend['InvoiceItems'] = array();
            // 將商品加入電子發票商品列表陣列
            foreach ($obj->Send['Items'] as $info)
            {
                array_push($obj->SendExtend['InvoiceItems'],array('Name' => $info['Name'],'Count' =>
                    $info['Quantity'],'Word' => '個','Price' => $info['Price'],'TaxType' => TaxType::Dutiable));
            }
            $obj->SendExtend['InvoiceRemark'] = '測試發票備註';
            $obj->SendExtend['DelayDay'] = '0';
            $obj->SendExtend['InvType'] = InvType::General;
            */


            //產生訂單(auto submit至AllPay)
            $obj->CheckOut();

        } catch (Exception $e)
        {
            echo $e->getMessage();
        }


    }

    public function make(Array $toBeSavedInfo, Request $request, Recipient $recipient)
    {
        DB::beginTransaction();
        try
        {
            $AllPay = new self();

            $AllPay->user_id = $toBeSavedInfo['user_id'];
            $AllPay->payment_service_id = $toBeSavedInfo['payment_service']->id;
            $AllPay->expiry_time = $toBeSavedInfo['expiry_time'];
            $AllPay->MerchantID = env('MERCHANTID');
            $AllPay->MerchantTradeNo = $toBeSavedInfo['merchant_trade_no'];
            $AllPay->MerchantTradeDate = $toBeSavedInfo['merchant_trade_date'];
            $AllPay->total_amount = $toBeSavedInfo['total_amount'];
            $AllPay->TradeDesc = $toBeSavedInfo['trade_desc'];
            $AllPay->ItemName = $toBeSavedInfo['orders_name'];
            $AllPay->recipient_id = $recipient->id;
            $AllPay->save();

            foreach ($toBeSavedInfo['orders'] as $order)
            {
                $order_relations = new OrderRelations();
                $order_relations->payment_service_id = $toBeSavedInfo['payment_service']->id;
                $order_relations->payment_service_order_id = $AllPay->id;
                $order_relations->order_id = $order->id;
                $order_relations->save();
            }
        } catch (Exception $e)
        {
            DB::rollBack();

            return 'something went wrong with DB';
        }
        DB::commit();
    }

    public static function checkIfPaymentPaid($RtnCode)
    {
        if ($RtnCode == 1)
            return true;

        return false;
    }

    public static function checkIfCheckMacValueCorrect($paymentResponse)
    {
        $parameters = $paymentResponse->except('CheckMacValue');
        $receivedCheckMacValue = $paymentResponse->CheckMacValue;
        $calculatedCheckMacValue = CheckMacValue::generate($parameters, env('HASHKEY'), env('HASHIV'), EncryptType::ENC_SHA256);
        if ($receivedCheckMacValue == $calculatedCheckMacValue)
            return true;

        return false;
    }

    public function listen(Request $request)
    {
        if (AllPay::checkIfCheckMacValueCorrect($request) && AllPay::checkIfPaymentPaid($request->RtnCode))
        {
            $AllPayPaymentOrder = (new AllPay)->where('MerchantTradeNo', $request->MerchantTradeNo)->first();
            $AllPayPaymentOrder->update([
                'status'               => 7,
                'expiry_time'          => null,
                'to_be_completed_date' => Carbon::now()->addDays(env('DAYS_OF_ORDER_TO_BE_COMPLETED'))->toDateTimeString(),
                'TradeNo'              => $request->TradeNo,
            ]);
            $recipient = $AllPayPaymentOrder->recipient;

            $orderRelations = $AllPayPaymentOrder->where('MerchantTradeNo', $request->MerchantTradeNo)->first()->orderRelations->where('payment_service_id', 1);
            Order::updateStatus($orderRelations, $recipient, 7);

            SendMailWhenPaymentPaid::dispatch($AllPayPaymentOrder, $orderRelations);

            return true;
        }

        return false;
    }

    public static function refund($order, $paymentServiceInstance, $orderRelation)
    {
        //載入SDK(路徑可依系統規劃自行調整)
        try
        {
            $obj = new AllInOne();

            //服務參數
            $obj->ServiceURL = "https://payment-stage.opay.tw/Cashier/AioChargeback";         //服務位置
            $obj->HashKey = env('HASHKEY');                                            //測試用Hashkey，請自行帶入AllPay提供的HashKey
            $obj->HashIV = env('HASHIV');                                            //測試用HashIV，請自行帶入AllPay提供的HashIV
            $obj->MerchantID = env('MERCHANTID');                                                      //測試用MerchantID，請自行帶入AllPay提供的MerchantID
            $obj->EncryptType = EncryptType::ENC_SHA256;                                        //CheckMacValue加密類型，請固定填入1，使用SHA256加密

            $obj->ChargeBack['MerchantTradeNo'] = $paymentServiceInstance->MerchantTradeNo;

            $obj->ChargeBack['TradeNo'] = $paymentServiceInstance->TradeNo;
            //訂單編號
            $obj->ChargeBack['ChargeBackTotalAmount'] = $order->total_amount;
            //退款金額
            $obj->AioChargeback();

        } catch (Exception $e)
        {
            return Helpers::result(true, 'Something wrong happened', 200);
            echo $e->getMessage();
        }


    }

}
