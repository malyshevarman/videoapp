<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrderReview;
use Illuminate\Http\Request;

class ServiceReviewController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $reviews = ServiceOrderReview::query()
            ->with('serviceOrder')
            ->whereHas('serviceOrder', fn ($query) => $query->visibleToUser($request->user()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('comment', 'like', '%' . $search . '%')
                        ->orWhereHas('serviceOrder', function ($serviceQuery) use ($search) {
                            $serviceQuery->where('order_id', 'like', '%' . $search . '%')
                                ->orWhere('client->customerFirstName', 'like', '%' . $search . '%')
                                ->orWhere('client->customerLastName', 'like', '%' . $search . '%')
                                ->orWhere('client->firstName', 'like', '%' . $search . '%')
                                ->orWhere('client->lastName', 'like', '%' . $search . '%');
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reviews.index', compact('reviews', 'search'));
    }
}
