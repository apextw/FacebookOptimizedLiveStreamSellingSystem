<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Order extends Model {

    protected $fillable = [
        'status', 'expiry_time', 'to_be_deleted_time'
    ];

    public function channel()
    {
        return $this->belongsTo('App\Channel', 'channel_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function item()
    {
        return $this->hasOne('App\Item', 'item_id', 'id');
    }

    public static function getOrders(Request $request)
    {
        return Order::where('user_id', User::getUserID($request))->get();
    }

    public static function getBuyerLatestChannel(Request $request)
    {
        return Order::where('user_id', User::getUserID($request))->latest()->first()->channel;
    }

    public static function getAllPlacedOrdersForBuyer(Request $request)
    {
        $orders = Order::getOrders($request);
        $response = Order::foreachAndRefineOrders($orders);

        return $response;
    }

    public static function checkIfUserPlacedOrders(Request $request)
    {
        if (Order::where('user_id', User::getUserID($request))->count() !== 0)
        {
            return true;
        }

        return false;
    }

    public static function foreachAndRefineOrders($orders)
    {
        foreach ($orders as $order)
        {
            if (($order->to_be_deleted_time !== null)
                && (Carbon::now()->gt($order->to_be_deleted_time)))
            {
                $order->delete();
                continue;
            }

            if (($order->to_be_deleted_time !== null)
                && (Carbon::now()->gt($order->expiry_time)))
            {
                $order->effective = 0;
                $order->save();
            }

            $response[] = [
                'id'                 => $order->id,
                'order'              => $order->name,
                'user_id'            => $order->user_id,
                'name'               => $order->item_name,
                'description'        => $order->item_description,
                'unit_price'         => $order->unit_price,
                'quantity'           => $order->quantity,
                'total_amount'       => $order->total_amount,
                'channel_id'         => $order->channel_id,
                'status'             => $order->status,
                'effective'          => $order->effective,
                'expiry_time'        => Carbon::parse($order->expiry_time)->toCookieString(),
                'time'               => $order->created_at->toCookieString(),
                'images'             => $order->images == null ? null : Item::getImageURL($order->images),
                'recipient'          => $order->recipient,
                'phone_code'         => $order->phone_code,
                'phone_number'       => $order->phone_number,
                'post_code'          => $order->post_code,
                'country'            => $order->country,
                'city'               => $order->city,
                'district'           => $order->district,
                'others'             => $order->others,
                'effective'          => $order->effective,
                'to_be_deleted_time' => Carbon::parse($order->to_be_deleted_time)->toCookieString()
            ];
        }

        return $response;
    }

    public static function getOrdersInLatestChannel(Request $request)
    {
        $latestOrder = Order::where('user_id', User::getUserID($request))->latest()->first();

        return Order::where('channel_id', $latestOrder->channel_id)->where('user_id', User::getUserID($request))->get();
    }

    public static function getProfitInDetail($channel_ID)
    {
        return DB::table('orders')
            ->where('status', 1)
            ->select(DB::raw('item_name, item_description, round(avg(cost)) as cost, round(avg(unit_price)) as unit_price, sum(profit) as profit, sum(total_cost) as total_cost, sum(quantity) as quantity, sum(total_amount) as turnover'))
            ->whereIn('channel_id', $channel_ID)
            ->groupBy('item_name', 'item_description', 'cost', 'unit_price')
            ->get();
    }

    public static function getProfitInDetailPerChannel($channel_ID)
    {
        return DB::table('orders')
            ->where('status', 1)
            ->select(DB::raw('item_name, item_description, round(avg(cost)) as cost, round(avg(unit_price)) as unit_price, sum(profit) as profit, sum(total_cost) as total_cost, sum(quantity) as quantity, sum(total_amount) as turnover'))
            ->where('channel_id', $channel_ID)
            ->groupBy('item_name', 'item_description', 'cost', 'unit_price')
            ->get();
    }

    public static function getTotalAmountForPayments($orders)
    {
        $totalAmount = 0;
        foreach ($orders as $order)
        {
            $totalAmount += $order->total_amount;
        }

        return $totalAmount;
    }

    public static function getOrdersNameForPayments($orders)
    {
        $ordersArray = [];
        foreach ($orders as $order)
        {
            $ordersArray[] = $order->name;
        }

        return implode(',', $ordersArray);
    }

    public static function getUserID($order_id)
    {
        return Order::where('id', $order_id)->first()->user_id;
    }

    public static function checkIfOrderPaid($orders)
    {
        foreach ($orders as $order)
        {
            if ($order->status == true)
                return true;
        }

        return false;
    }

    public static function updateStatus($orderRelations)
    {
        foreach ($orderRelations as $orderRelation)
        {
            $orderRelation->order->update(['status' => 1, 'expiry_time' => null, 'to_be_deleted_time' => null]);
        }
    }

    public static function checkIfOrderExpired($orders)
    {
        foreach ($orders as $order)
        {
            if ($order->effective == 0)
            {
                return true;
            }
        }

        return false;
    }

    public static function createOrderAndGetInstance(Request $request, Item $item, Recipient $recipient)
    {
        $total_cost = $item->cost * $request->number;
        $total_amount = $item->unit_price * $request->number;
        $profit = $total_amount - $total_cost;
        $orderName = time() . Helpers::createAUniqueNumber();
        $buyer = User::getUser($request);

        $order = new Order();
        $order->name = $orderName;
        $order->user_id = $buyer->id;
        $order->item_name = $item->name;
        $order->item_description = $item->description;
        $order->unit_price = $item->unit_price;
        $order->cost = $item->cost;
        $order->total_cost = $item->cost * $request->number;
        $order->profit = $profit;
        $order->quantity = $request->number;
        $order->total_amount = $total_amount;
        $order->channel_id = $buyer->channel_id;
        $order->images = $item->images;
        $order->recipient = $recipient->name;
        $order->phone_code = $recipient->phone->phone_code;
        $order->phone_number = $recipient->phone->phone_number;
        $order->post_code = $recipient->postcode;
        $order->country = DB::table('country')->where('iso', $recipient->country_code)->first()->nicename;
        $order->city = $recipient->city;
        $order->district = $recipient->district;
        $order->others = $recipient->others;
        $order->expiry_time = Carbon::now()->addDays(env('DAYS_OF_ORDER_TO_BE_EXPIRED'))->toDateTimeString();
        $order->to_be_deleted_time = Carbon::now()->addDays(env('DAYS_OF_ORDER_TO_BE_DELETED'))->toDateTimeString();
        $order->save();

        return $order;
    }
}
