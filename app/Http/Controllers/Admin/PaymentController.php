<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        $status = request('status','all');
        $payments = Payment::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when(request('q'), fn($q) => $q->whereHas('order', fn($o) => $o->where('order_number','like','%'.request('q').'%')))
            ->with(['order.buyer','order.seller'])
            ->latest()->paginate(20);
        return view('admin.payments', compact('payments','status'));
    }
}
