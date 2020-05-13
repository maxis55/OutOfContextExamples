<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Admin\Stores\Repositories\StoreRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\Front\ReviewsResource;
use App\Http\Resources\Front\StoreItemResourceCollection;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class StoreController extends Controller
{
    public function show(string $slug)
    {
        session()->flash('previous-store-slug', $slug);

        $store = Store::where('slug', $slug)->first();
        if ( ! $store) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $reviews = ReviewsResource::collection($store->reviews()
            ->orderBy('created_at', 'desc')->get());

        return view('front.store.show', compact('store', 'reviews'));
    }

    public function storePanel()
    {
        $store = auth()->user()->store;
        if ( ! $store) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return view('front.store.admin', compact('store'));
    }

    public function storePanelFetch(Request $request)
    {
        $per_page = $request->input('per_page') ?? 5;
        $search   = $request->input('search');
        $store    = auth()->user()->store;

        $store_items = $store->store_items()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            });

        return new StoreItemResourceCollection($store_items->paginate($per_page));
    }

    public function storePanelUpdate(Request $request)
    {
        $store = auth()->user()->store;
        $arr   = $request->only([
            'content',
            'promo_code_name',
            'promo_code_discount'
        ]);

        if ($request->file('logo')) {
            $arr['logo_url']
                = StoreRepository::saveImage($request->file('logo'));
        }
        $store->update($arr);

        return back();

    }

    public function storePanelUpdateProduct(Request $request)
    {
        $store_item_id      = $request->input('store_item_id');
        $store              = auth()->user()->store;
        $store_item         = $store->store_items()
            ->where('store_items.id', $store_item_id)
            ->first();
        $store_item->hidden = ! $store_item->hidden;
        $store_item->save();

        return \response(['data' => 'success']);
    }


    public function createReview(Request $request, Store $store)
    {

        $vk_id = auth('vk_user')->user()->vk_id;

        if ($store->reviews()->where('vk_user_id', $vk_id)->count() > 0) {
            return \response(['data' => 'already_exists'],
                Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $params               = $request->all();
        $params['vk_user_id'] = $vk_id;

        $store->reviews()->create($params);

        return \response(['data' => 'success']);
    }
}
