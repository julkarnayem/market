<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;

class OfferController extends Controller
{
    public function index()
    {
        $status = request('status','all');
        $offers = Offer::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->with(['asset','buyer','seller'])
            ->latest()->paginate(20);
        return view('admin.offers', compact('offers','status'));
    }
}
