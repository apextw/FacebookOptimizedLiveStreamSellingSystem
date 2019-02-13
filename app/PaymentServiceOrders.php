<?php

namespace App;

use AllInOne;
use Carbon\Carbon;
use CheckMacValue;
use EncryptType;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PaymentMethod;

class PaymentServiceOrders extends Model {

    protected $fillable = ['status', 'expiry_time'];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function orderRelations()
    {
        return $this->hasMany('App\OrderRelations', 'payment_service_order_id', 'id');
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


            $obj->Send['ReturnURL'] = env('ALLPAYRETURNURL');
            $obj->Send['ClientBackURL'] = $request->ClintBackURL;
            $obj->Send['MerchantTradeNo'] = $toBeSavedInfo['merchant_trade_no'];                                 //訂單編號
            $obj->Send['MerchantTradeDate'] = $toBeSavedInfo['merchant_trade_date'];                              //交易時間
            $obj->Send['TotalAmount'] = $toBeSavedInfo['total_amount'];                                             //交易金額
            $obj->Send['TradeDesc'] = $toBeSavedInfo['trade_desc'];                                  //交易描述
            $obj->Send['ChoosePayment'] = PaymentMethod::ALL;                           //付款方式:Credit

            //訂單的商品資料
            array_push($obj->Send['Items'], array('Name'     => $toBeSavedInfo['orders_name'],
                                                  'Price'    => (int) $toBeSavedInfo['total_amount'],
                                                  'Currency' => "元",
                                                  'Quantity' => (int) $toBeSavedInfo['quantity'],
                                                  'URL'      => "dedwed"));


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

    public function make(Array $toBeSavedInfo, Request $request)
    {
        DB::beginTransaction();
        try
        {
            $payment_service_order = new self();

            $payment_service_order->user_id = $toBeSavedInfo['user_id'];
            $payment_service_order->payment_service_id = $toBeSavedInfo['payment_service']->id;
            $payment_service_order->expiry_time = $toBeSavedInfo['expiry_time'];
            $payment_service_order->MerchantID = env('MERCHANTID');
            $payment_service_order->MerchantTradeNo = $toBeSavedInfo['merchant_trade_no'];
            $payment_service_order->MerchantTradeDate = $toBeSavedInfo['merchant_trade_date'];
            $payment_service_order->total_amount = $toBeSavedInfo['total_amount'];
            $payment_service_order->TradeDesc = $toBeSavedInfo['trade_desc'];
            $payment_service_order->ItemName = $toBeSavedInfo['orders_name'];
            $payment_service_order->save();

            foreach ($toBeSavedInfo['orders'] as $order)
            {
                $order_relations = new OrderRelations();
                $order_relations->payment_service_id = $toBeSavedInfo['payment_service']->id;
                $order_relations->payment_service_order_id = $payment_service_order->id;
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

    public static function deleteExpiredOrders()
    {
        $toBeDeletedPaymentServiceOrders = (new PaymentServiceOrders)->where('expiry_time', '<', Carbon::now());
        foreach ($toBeDeletedPaymentServiceOrders->get() as $toBeDeletedPaymentServiceOrder)
        {
            $orderRelations = $toBeDeletedPaymentServiceOrder->orderRelations;
            foreach ($orderRelations as $orderRelation)
                $orderRelation->delete();
        }
        $toBeDeletedPaymentServiceOrders->delete();
    }
}
