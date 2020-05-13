<?php

namespace App\Models;

use App\Traits\HasSeoTrait;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Rennokki\QueryCache\Traits\QueryCacheable;

/**
 * Class UniqueItem
 *
 *
 * @property int                        $id
 * @property string                     $name
 * @property string                     $slug
 * @property boolean                    $is_steam_item
 * @property boolean                    $hidden
 * @property string                     $steam_appid
 * @property double                     $steam_price
 * @property double                     $min_price_restriction
 * @property boolean                    $deleted_from_steam
 * @property boolean                    $is_free
 * @property boolean                    $parsed_lately
 * @property int                        $views
 * @property string                     $type
 * @property string                     $header_image
 * @property string                     $steam_url
 * @property string                     $steam_type
 *
 * @property bool                       $has_trading_cards
 * @property bool                       $has_low_confidence_metric
 * @property bool                       $has_profile_features_limited
 *
 * @property SteamCategory[]|Collection $categories
 * @property SteamGenre[]|Collection    $genres
 * @property StoreItem[]|Collection     $store_items
 * @property StoreItem[]|Collection     $viable_store_items
 *
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 *
 * @package App\Models
 */
class UniqueItem extends Model
{
    use Sluggable, HasSeoTrait, QueryCacheable;

    public $cacheFor = 3600; // cache time, in seconds
    protected static $flushCacheOnUpdate = true;

    const STEAM_TYPE_APP = 'app';
    const STEAM_TYPE_SUB = 'sub';
    const STEAM_TYPE_BUNDLE = 'bundle';

    const STEAM_TYPES
        = [
            self::STEAM_TYPE_APP,
            self::STEAM_TYPE_SUB
        ]; //steam api only answers to these

    const TYPE_GAMES = ['game', 'Game']; //steam and steamdb description

    const TYPE_DLC
        = [
            'dlc',
            'Downloadable Content'
        ]; //steam and steamdb description

    const APPENDS
        = [
            'has_trading_cards',
            'has_low_confidence_metric',
            'has_profile_features_limited'
        ];

    protected $fillable
        = [
            'name',
            'is_steam_item',
            'steam_appid',
            'steam_price',
            'min_price_restriction',
            'deleted_from_steam',
            'is_free',
            'type',
            'header_image',
            'views',
            'steam_url',
            'steam_type',
            'hidden',
            'parsed_lately'
        ];

    protected $appends
        = self::APPENDS;

    protected $casts
        = [
            'is_steam_item'         => 'bool',
            'deleted_from_steam'    => 'bool',
            'is_free'               => 'bool',
            'hidden'                => 'bool',
            'parsed_lately'         => 'bool',
            'min_price_restriction' => 'double',
            'steam_price'           => 'double'
        ];

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source'   => 'name',
                'onUpdate' => true,
            ]
        ];
    }

    public function scopeHasViableStoreItems($query)
    {
        return $query->whereHas(
            'viable_store_items'
        );
    }

    public function getHasTradingCardsAttribute()
    {
        return $this->categories->pluck('id')
            ->contains(SteamCategory::STEAM_TRADING_CARDS);
    }

    public function getHasLowConfidenceMetricAttribute()
    {
        return $this->categories->pluck('id')
            ->contains(SteamCategory::LOW_CONFIDENCE_METRIC);
    }

    public function getHasProfileFeaturesLimitedAttribute()
    {
        return $this->categories->pluck('id')
            ->contains(SteamCategory::PROFILE_FEATURES_LIMITED);
    }

    public function getCanBeSoldInBatchesAttribute()
    {
        return $this->viable_store_items()
                ->whereHas('parser', function ($query) {
                    $query->where('parsers.type', Parser::TYPE_LEQUISHOP);
                })
                ->where('amount_of_keys', '>', 1)
                ->count() > 0;
    }

    /**
     * @return Builder
     */
    public function viable_store_items()
    {
        return $this
            ->store_items()
            ->shown()
            ->viable()
            ->join('unique_items', 'unique_items.steam_appid', '=',
                'store_items.steam_id')
            ->where(function ($query) {
                $query->whereColumn('price', '>', 'min_price_restriction')
                    ->orWhereNull('min_price_restriction');
            });
        //should only be used with small samples, very resource heavy query(but tests for everything that is required to be shown in catalog)
        //since it already has HasMany, its a join, but eloquent transforms it into optimized query when used with function whereHas()

        //however second join is needed for `min_price_restriction` column
    }

    public function store_items()
    {
        return $this->hasMany(StoreItem::class, 'steam_id', 'steam_appid');
    }

    public function getMinPriceAttribute()
    {
        $min_price_el = $this->getMinPriceEl();

        if ( ! $min_price_el) {
            return null;
        }

        return $min_price_el
            ->discounted_price;
    }

    /**
     * @return StoreItem|null
     */
    private function getMinPriceEl()
    {

        $el = $this->viable_store_items
            ->filter(function ($el) {
                return $el->discounted_price > 0;
            })
            //sort by price, then by priority of stores
            ->sort(function ($a, $b) {
                if ($a->discounted_price === $b->discounted_price) {
                    if ($a->store->priority === $b->store->priority) {
                        return 0;
                    }

                    return $a->store->priority < $b->store->priority ? -1 : 1;
                }

                return $a->discounted_price < $b->discounted_price ? -1 : 1;
            })
            ->first();

        return $el;
    }

    public function getMinPriceLinkAttribute()
    {
        $min_price_el = $this->getMinPriceEl();

        if ( ! $min_price_el) {
            return null;
        }

        if ($min_price_el->store->add_parameter_to_link) {
            return $min_price_el->url
                . $min_price_el->store->end_of_link_to_add;
        } else {
            return $min_price_el->url;
        }

    }

    public function genres()
    {
        return $this->belongsToMany(SteamGenre::class)->withTimestamps();
    }

    public function categories()
    {
        return $this->belongsToMany(SteamCategory::class)->withTimestamps();
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
        return $query->where('unique_items.hidden', false);
    }
}
