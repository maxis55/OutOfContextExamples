<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

/**
 * Class StoreItem
 *
 * @property int     $id
 * @property string  $remote_id
 * @property string  $name
 * @property double  $price
 * @property double  $discounted_price
 * @property string  $url
 * @property string  $region
 * @property string  $platform
 * @property string  $img_hint
 * @property boolean $hidden
 * @property int     $amount_of_keys
 * @property int     $sim                amount of similar character
 *
 * @property int     $store_id
 * @property int     $unique_item_id
 * @property int     $parser_id
 *
 * @property string  $steam_id
 * @property bool    $tried_search
 * @property bool    $tried_second_search
 * @property bool    $certain_fit
 * @property bool    $is_steam
 * @property bool    $deleted_from_store
 * @property double  $percentage_fit
 * @property string  $steam_type
 *
 * @property Carbon  $created_at
 * @property Carbon  $updated_at
 *
 * @package App\Models
 */
class StoreItem extends Model
{

    use QueryCacheable;
    public $cacheFor = 3600; // cache time, in seconds
    protected static $flushCacheOnUpdate = true;

    const APPENDS = ['discounted_price'];

    protected $fillable
        = [
            'remote_id',
            'name',
            'price',
            'url',
            'region',
            'platform',
            'img_hint',
            'hidden',
            'amount_of_keys',
            'store_id',
            'unique_item_id',
            'parser_id',
            'is_steam',
            'steam_id',
            'steam_type',
            'certain_fit',
            'deleted_from_store'
        ];

    protected $casts
        = [
            'hidden'              => 'bool',
            'certain_fit'         => 'bool',
            'tried_search'        => 'bool',
            'tried_second_search' => 'bool',
            'is_steam'            => 'bool',
            'deleted_from_store'  => 'bool',
            'price'               => 'double'
        ];

    protected $appends
        = self::APPENDS;

    /**
     * @return array
     */
    public static function getMinMaxPriceKeys(): array
    {
        $prices_set_up = self::select([
            'price',
            'percentage_fit',
            'certain_fit',
            'amount_of_keys',
            'hidden',
            'steam_id'
        ])
            ->whereHas(
                'unique_item'
            )
            ->shown()
            ->viable()
            ->get();

        return [
            'min_price' => $prices_set_up->min('price'),
            'max_price' => $prices_set_up->max('price'),
            'min_keys'  => $prices_set_up->min('amount_of_keys'),
            'max_keys'  => $prices_set_up->max('amount_of_keys')
        ];
    }

    /**
     * Scope a query to only include not hidden items
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeShown(Builder $query)
    {
        return $query->where('store_items.hidden', false);
    }

    /**
     * Scope a query to only include viable items
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeViable(Builder $query)
    {
        return $query
//            ->where(
//            function ($query) {
////                $query->where('percentage_fit', '>=', 99)
////                    ->orWhere('certain_fit', true);
//            }
//        )
            ->where('price', '>', 0)
            ->where('store_items.deleted_from_store', false)
            ->where(function ($query) {
                $query->whereNull('store_items.amount_of_keys')
                    ->orWhere('store_items.amount_of_keys', '>', 0);
            })
//            ->when($this->unique_item,function ($query){
//                $query ->where('price','>',$this->unique_item->min_price_restriction);
//            })
            ;

    }

    public function getDiscountedPriceAttribute()
    {
        $discount = $this->store->promo_code_discount;
        if ($discount) {
            $new_price = (float)$this->price * (100 - $discount) / 100;

            if ((int)$new_price != $new_price) {
                return number_format((float)$this->price * (100 - $discount)
                    / 100,
                    2, '.', '');
            }

            return $new_price;
        }

        return $this->price;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function unique_item()
    {
        return $this->belongsTo(UniqueItem::class, 'steam_id', 'steam_appid');
    }

    public function parser()
    {
        return $this->belongsTo(Parser::class);
    }
}
