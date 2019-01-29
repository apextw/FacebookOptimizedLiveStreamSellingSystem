<?php

namespace App\Http\Controllers;

use App\Channel;
use App\Helpers;
use App\Item;
use App\Order;
use App\StreamingItem;
use App\Token;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Input;

class OrdersController extends Controller {

    public function create(Request $request, Item $item)
    {
        if (!User::checkIfUserInAChannel($request))
            return Helpers::result(false, 'You have to be in a channel', 400);

        if (User::checkIfUserIsAHost($request))
            return Helpers::result(false, 'This operation is only allowed for buyers', 400);

        if (!StreamingItem::checkIfRemainingQuantityEnough($request->number, $item->stock))
            return Helpers::result(false, 'The required quantity is not enough', 400);

        $buyer = User::getUser($request);
        $streamingItem = StreamingItem::getStreamingItems($buyer->channel_id);

        if ((!StreamingItem::checkIfItemOnStream($streamingItem, $item)))
            return Helpers::result(false, 'The item is not currently on the stream', 400);

        $orderName = time() . Helpers::createAUniqueNumber();
        Order::forceCreate([
            'name'             => $orderName,
            'user_id'          => $buyer->id,
            'item_name'        => $item->name,
            'item_description' => $item->description,
            'unit_price'       => $item->unit_price,
            'cost'             => $item->cost,
            'quantity'         => $request->number,
            'total_amount'     => $item->unit_price * $request->number,
            'channel_id'       => $buyer->channel_id,
            'images'           => $item->images
        ]);


        StreamingItem::updateRemainingQuantity($streamingItem, $request->number);

        return Helpers::result(true, 'Your order has been successfully placed', 200);
    }

    public function getBuyerOrders(Request $request)
    {
        $orders = User::getUser($request)->order;
        $response = Order::foreachOrders($orders);

        return Helpers::result(true, $response, 200);
    }

    public function getOrdersInLatestChannel(Request $request)
    {
        if (!Order::checkIfUserPlacedOrders($request))
            return Helpers::result(true, [], 200);

        $orders = Order::getOrdersInLatestChannel($request);
       $response = Order::foreachOrders($orders);

        return Helpers::result(true, $response, 200);
    }

    public function getSellerOrders(Request $request)
    {
        $response = User::getUser($request)->getAllSellerOrders();

        return Helpers::result(true, $response, 200);

    }

    public function getSellerOrder(Request $request, Channel $channel)
    {

        if ($channel->user_id !== User::getUserID($request))
            return Helpers::result(false, 'Invalid parameters', 400);
        if ($channel->order->count() == 0)
            return Helpers::result(true, '[]', 200);

        $orders = $channel->order;
        $response = Order::foreachOrders($orders);

        return Helpers::result(true, $response, 200);

    }

}












