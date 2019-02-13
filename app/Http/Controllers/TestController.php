<?php

namespace App\Http\Controllers;

use AllInOne;
use App\Helpers;
use App\Item;
use App\Mail\OrderCreated;
use App\Order;
use App\Token;
use App\User;
use EncryptType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use PaymentMethod;

class TestController extends Controller {

    public function test(Request $request)
    {
        $array = [
            'mc_gross' => '8400',
            'protection_eligibility' => 'Eligible',
            'address_status' => 'confirmed',
            'payer_id' => 'K2LA8T8C9V9KN',
            'address_street' => 'NO 1 Nan Jin Road',
            'payment_date' => '09:50:22 Feb 13, 2019 PST',
            'payment_status' => 'Completed',
            'charset' => 'gb2312',
            'address_zip' => '200000',
            'first_name' => 'Ray',
            'mc_fee' => '296',
            'address_country_code' => 'CN',
            'address_name' => '�� Ray',
            'notify_version' => '3.9',
            'custom' => '1550080207mEoDn1',
            'payer_status' => 'verified',
            'business' => 'BuyBuyBuyGoGo@gmail.com',
            'address_country' => 'China',
            'address_city' => 'Shanghai',
            'quantity' => '1',
            'verify_sign' => 'A13wSS6W8MDQL77GRIL-d6cZ3vafATBFLTWpF9Ma9OyQschnhU-1iZOQ',
            'payer_email' => 'tn710617@gmail.com',
            'txn_id' => '10353632E5962661J',
            'payment_type' => 'instant',
            'last_name' => '��',
            'address_state' => 'Shanghai',
            'receiver_email' => 'BuyBuyBuyGoGo@gmail.com',
            'payment_fee' => NULL,
            'shipping_discount' => '0',
            'insurance_amount' => '0',
            'receiver_id' => 'QHQ292JDSRMAJ',
            'txn_type' => 'web_accept',
            'item_name' => '1549098719R1CGst',
            'discount' => '0',
            'mc_currency' => 'TWD',
            'item_number' => NULL,
            'residence_country' => 'CN',
            'test_ipn' => '1',
            'shipping_method' => 'Default',
            'transaction_subject' => NULL,
            'payment_gross' => NULL,
            'ipn_track_id' => '9c4e43ef9d024',
        ];
        dd(json_encode($array));
    }
}
