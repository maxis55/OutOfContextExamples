<?php


namespace App\Http\Controllers\Front;


use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Front\ProductResource;
use App\Http\Resources\Front\ProductResourceCollection;
use App\Models\SteamCategory;
use App\Models\UniqueItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        /**
         * @var UniqueItem $product
         */
        $product = UniqueItem::where('slug', $slug)
            ->hasViableStoreItems()
            ->with([
                'viable_store_items' => function ($query) {
                    $query->select([
                        'price',
                        'steam_id',
                        'store_id',
                        'url',
                        'amount_of_keys'
                    ])
                        ->orderBy('store_items.price')
                        ->with(['store']);
                },
                'genres',
                'categories'
            ])
            ->first();

        if ( ! $product) {
            abort(Response::HTTP_NOT_FOUND);
        }
        $product->increment('views');

        $sorted_store_items = $product->viable_store_items->sort(function (
            $a,
            $b
        ) {
            if ($a->discounted_price === $b->discounted_price) {
                if ($a->store->priority === $b->store->priority) {
                    return 0;
                }

                return $a->store->priority < $b->store->priority ? -1 : 1;
            }

            return $a->discounted_price < $b->discounted_price ? -1 : 1;
        });

        //get products with same exact list of genres
        $similar_products_query = UniqueItem::query();

        foreach ($product->genres->pluck('id') as $genre_id) {
            $similar_products_query->whereHas('genres',
                function ($query) use ($genre_id) {
                    $query->where('steam_genres.id', $genre_id);
                });
        }

        $similar_products = $similar_products_query
            ->hasViableStoreItems()
            ->with([
                'viable_store_items' => function ($query) {
                    $query->select(['price', 'steam_id', 'store_id', 'url'])
                        ->orderBy('store_items.price')
                        ->with(['store']);
                },
                'genres',
                'categories'
            ])
            ->where('unique_items.id', '!=', $product->getKey())
            ->orderByRaw('RAND()')
            ->limit(20)
            ->get();


        $similar_products
            = ProductResource::collection($similar_products)
            ->toArray(null);

        return view('front.product.show',
            compact('product', 'similar_products', 'sorted_store_items'));
    }

    public function getProducts(Request $request)
    {

        $per_page = $request->input('per_page');

        $genres            = $request->input('genres');
        $categories        = $request->input('categories');
        $stores            = $request->input('stores');
        $sort_by           = $request->input('sort_by');
        $price_range       = $request->input('price_range');
        $steam_price_range = $request->input('steam_price_range');

        //not all store_items have keys, so using input from keys_range no longer provides all products
        //commented until solution is found/agreed upon
//        $keys_range        = $request->input('keys_range');
        $keys_range = [];

        $game_filters = $request->input('game_filters') ?? [];

        $deleted_from_steam = in_array('deleted_from_steam', $game_filters);
        $is_dlc             = in_array('is_dlc', $game_filters);
        $is_sub             = in_array('is_sub', $game_filters);


        $query = UniqueItem::query();

        $query->shown();

        $query->when($steam_price_range && $steam_price_range[0] != 'null'
            && $steam_price_range[1] != 'null',
            function ($query) use ($steam_price_range, $deleted_from_steam) {
                $query->where(function ($query) use (
                    $steam_price_range,
                    $deleted_from_steam
                ) {
                    $query->whereBetween('steam_price', $steam_price_range)
                        ->when($deleted_from_steam, function ($query) {
                            $query->orWhere('steam_price', null);
                        });
                });

            });

        $query->when($genres, function ($query) use ($genres) {
            $query->whereHas(
                'genres',
                function ($query) use ($genres) {
                    $query->whereIn('steam_genres.id', $genres);
                }
            );
        });

        if (is_array($categories)
            && in_array(SteamCategory::LOW_CONFIDENCE_METRIC, $categories)
        ) {
            $categories[] = SteamCategory::PROFILE_FEATURES_LIMITED;
        }
        $query->when($categories, function ($query) use ($categories) {
            $query->whereHas(
                'categories',
                function ($query) use ($categories) {
                    $query->whereIn('steam_categories.id', $categories);
                }
            );
        });


        $query->when($deleted_from_steam, function ($query) {
            $query->where('deleted_from_steam', true);
        });

        $query->when($is_dlc, function ($query) {
            $query->whereIn('type', UniqueItem::TYPE_DLC);
        });

        $query->when($is_sub, function ($query) {
            $query->where('steam_type', UniqueItem::STEAM_TYPE_SUB);
        });


        //only get unique items with good/viable store_items
        $query->whereHas(
            'viable_store_items',
            function ($query) use ($stores, $price_range, $keys_range) {
                $query->when($stores, function ($query) use ($stores) {
                    $query->whereHas(
                        'store',
                        function ($query) use ($stores) {
                            $query->whereIn('stores.id', $stores);
                        }
                    );
                })
                    ->when($price_range && $price_range[0] != 'null'
                        && $price_range[1] != 'null',
                        function ($query) use ($price_range) {
                            $query->whereBetween('price', $price_range);
                        })
                    ->when($keys_range && $keys_range[0] != 'null'
                        && $keys_range[1] != 'null',
                        function ($query) use ($keys_range) {
                            $query->whereBetween('amount_of_keys', $keys_range);
                        }
                    );
            }
        );


        $query->with([
            'viable_store_items' => function ($query) use (
                $stores,
                $price_range,
                $keys_range
            ) {
                $query->select(['price', 'steam_id', 'store_id', 'url'])
                    ->when($stores, function ($query) use ($stores) {
                        $query->whereHas(
                            'store',
                            function ($query) use ($stores) {
                                $query->whereIn('stores.id', $stores);
                            }
                        );
                    })
                    ->with([
                        'store' => function ($query) {
                            $query->select([
                                'id',
                                'end_of_link_to_add',
                                'promo_code_discount',
                                'add_parameter_to_link',
                                'priority'
                            ]);
                        }
                    ])
                    ->when($price_range && $price_range[0] != 'null'
                        && $price_range[1] != 'null',
                        function ($query) use ($price_range) {
                            $query->whereBetween('price', $price_range);
                        })
                    ->when($keys_range && $keys_range[0] != 'null'
                        && $keys_range[1] != 'null',
                        function ($query) use ($keys_range) {
                            $query->whereBetween('amount_of_keys', $keys_range);
                        }
                    )
                    ->orderBy('store_items.price');
            },
            'categories'
        ]);


        //need to sort using collections, because of complicated price sorting


        switch ($sort_by) {
            case 'new':
                $query->orderByDesc('created_at');
                break;
            case 'price,asc':
                $query->orderBy('discounted_price');
                break;
            case 'price,desc':
                $query->orderByDesc('discounted_price');
                break;
            default: //popularity
                $query->orderByDesc('views');
                break;
        }
        $paginator = $query->paginate($per_page);

        return new ProductResourceCollection(
            $paginator
        );
    }


    public function getSearchResults(Request $request)
    {
        $string = $request->input('s');
        if ( ! $string) {
            return [];
        }

        $unique_items = UniqueItem::where('name', 'like', '%' . $string . '%')
            ->shown()
            ->hasViableStoreItems()
            ->with([
                'viable_store_items' => function ($query) {
                    $query->select(['price', 'steam_id', 'store_id', 'url'])
                        ->with([
                            'store' => function ($query) {
                                $query->select([
                                    'id',
                                    'end_of_link_to_add',
                                    'promo_code_discount',
                                    'add_parameter_to_link',
                                    'priority'
                                ]);
                            }
                        ])
                        ->orderBy('store_items.price');
                },
                'categories'
            ])
            ->orderByRaw(
                'CASE
                    WHEN unique_items.type = "Game" THEN 2
                    WHEN unique_items.type = "sub" THEN 1
                    WHEN unique_items.type = "Downloadable Content" THEN 0
                END desc'
            )
            ->limit(30)
            ->get();

        return ProductResource::collection($unique_items);
    }
}
