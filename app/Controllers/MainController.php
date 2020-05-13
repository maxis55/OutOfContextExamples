<?php


namespace App\Http\Controllers\Front;


use App\Http\Controllers\Controller;
use App\Http\Resources\Front\ProductResource;
use App\Models\SteamCategory;
use App\Models\SteamGenre;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\UniqueItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MainController extends Controller
{
    public function index()
    {
        $expiresAt = Carbon::now()->addMinutes(60);


        if (Cache::has('main_min_map_price_keys')) {
            $arr = Cache::get('main_min_map_price_keys');
        } else {
            $arr = StoreItem::getMinMaxPriceKeys();
            Cache::put('main_min_map_price_keys', $arr, $expiresAt);
        }



        if (Cache::has('main_min_steam_price')
            && Cache::has('main_max_steam_price')
        ) {
            $arr['min_steam_price'] = Cache::get('main_min_steam_price');
            $arr['max_steam_price'] = Cache::get('main_max_steam_price');
        } else {
            $steam_price_setup = UniqueItem::select([
                'name',
                'steam_price',
                'steam_appid'
            ])
//            ->where('steam_price', '!==', null)
                ->whereHas(
                    'store_items',
                    function ($query) {
                        $query->select([
                            'price',
                            'steam_id',
                            'percentage_fit',
                            'certain_fit'
                        ])
                            ->shown()
                            ->viable();
                    }
                )
                ->get();
            $arr['min_steam_price'] = $steam_price_setup->min('steam_price');
            $arr['max_steam_price'] = $steam_price_setup->max('steam_price');

            Cache::put('main_min_steam_price', $arr['min_steam_price'],
                $expiresAt);

            Cache::put('main_max_steam_price', $arr['max_steam_price'],
                $expiresAt);
        }




        if (Cache::has('main_stores')) {
            $stores = Cache::get('main_stores');
        } else {
            $stores = Store::select(['id', 'name'])
                ->whereHas('store_items', function ($query) {
                    $query->whereHas('unique_item')
                        ->shown()
                        ->viable();
                })
                ->with('translations')
                ->get();
            Cache::put('main_stores', $stores, $expiresAt);
        }

        if (Cache::has('main_genres')) {
            $genres = Cache::get('main_genres');
        } else {
            $genres = SteamGenre::select(['id'])
                ->whereHas('unique_items', function ($query) {
                    $query->whereHas(
                        'store_items',
                        function ($query) {
                            $query->select([
                                'price',
                                'steam_id',
                                'percentage_fit',
                                'certain_fit'
                            ])
                                ->shown()
                                ->viable();
                        }
                    );
                })
                ->with('translations')
                ->get();
            Cache::put('main_genres', $genres, $expiresAt);
        }


        $arr['stores'] = $stores;
        $arr['genres'] = $genres;

        $arr['categories'] = [
            [
                'value' => SteamCategory::STEAM_TRADING_CARDS,
                'label' => 'Наличие коллекционных карт'
            ],
            [
                'value' => SteamCategory::STEAM_ACHIEVEMENTS,
                'label' => 'Наличие достижений'
            ],
            [
                'value' => SteamCategory::LOW_CONFIDENCE_METRIC,
                'label' => 'Игры без доверия'
            ]
        ];

        $arr['game_filter_options']
            = [
            [
                'value' => 'deleted_from_steam',
                'label' => 'Игры удалённые в Steam'
            ],
            [
                'value' => 'is_dlc',
                'label' => 'DLC'
            ],
            [
                'value' => 'is_sub',
                'label' => 'Паки'
            ],
        ];


        if (setting('index.featured_products')) {

            if (Cache::has('main_featured_products')) {
                $arr['featured_products']
                    = Cache::get('main_featured_products');
            } else {
                $arr['featured_products']
                    = ProductResource::collection(
                    UniqueItem::whereIn('id',
                        setting('index.featured_products'))
                        ->with([
                            'store_items' => function ($query) {
                                $query->shown()
                                    ->viable()
                                    ->orderBy('store_items.price')
                                    ->with([
                                        'store' => function ($query) {
                                            $query->select([
                                                'id',
                                                'end_of_link_to_add',
                                                'add_parameter_to_link'
                                            ]);
                                        }
                                    ]);
                            }
                        ])
                        ->get()
                )->toArray(null);

                Cache::put('main_featured_products', $arr['featured_products'],
                    $expiresAt);
            }

        } else {
            $arr['featured_products'] = [];
        }




        return view(
            'front.front-page',
            $arr
        );
    }
}
