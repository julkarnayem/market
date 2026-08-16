<?php
namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->buyer_user_id
            || $user->id === $order->seller_user_id
            || $user->isAdmin();
    }
    public function deliver(User $user, Order $order): bool
    {
        return $user->id === $order->seller_user_id && $order->status->canBeDelivered();
    }
    public function complete(User $user, Order $order): bool
    {
        return $user->id === $order->buyer_user_id && $order->status->canBeCompleted();
    }
    public function openDispute(User $user, Order $order): bool
    {
        return $user->id === $order->buyer_user_id && $order->status->canOpenDispute();
    }
}
