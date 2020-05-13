<?php

namespace App\Http\Controllers\Admin\Dealers\Repositories;


use App\Imports\PriceImport;
use App\Models\Dealer;
use App\Models\Manufacturer;
use App\Repositories\Base\BaseRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use XBase\Table;

/**
 * Class DealerRepository
 *
 * @property Dealer $model;
 *
 * @package App\Http\Controllers\Admin\Dealers\Repositories
 */
class DealerRepository extends BaseRepository implements
    DealerRepositoryInterface
{

    const FILE_XLS = 'xls';
    const FILE_XLSX = 'xlsx';
    const FILE_CSV = 'csv';
    const FILE_DBF = 'dbf';

    const FOLDER = 'dealers';

    const FILE_TYPES
        = [
            0 => self::FILE_XLS,
            1 => self::FILE_XLSX,
            2 => self::FILE_DBF,
            3 => self::FILE_CSV,
        ];


    public function __construct(Dealer $dealer)
    {
        parent::__construct($dealer);
    }


    /**
     * @param string $path
     *
     * @return array|Collection
     */
    public function processFile($path)
    {
        $rows     = collect([]);
        $infoPath = pathinfo(storage_path('app/' . $path));
        if ($infoPath['extension'] == self::FILE_DBF) {
            $table = new Table(storage_path('app/' . $path), null, 'CP866');

            $rows   = [];
            $rows[] = collect(array_keys($table->getColumns()));

            while ($record = $table->nextRecord()) {
                $curr_row = [];

                foreach ($table->getColumns() as $column) {
                    //hack to get string to behave when converted to json
                    $curr_row[] = $record->{$column->name};
                }
                $rows[] = collect($curr_row);

            }
            $rows = collect($rows);
        } else {
            $import = new PriceImport($this->model->csv_separator);
            Excel::import($import, storage_path('app/' . $path));
            $rows = $rows->merge($import->getRows());
        }

        return $rows;
    }

    /**
     * @param Collection $rows
     * @param array      $column_options
     *
     * @return array|Collection
     */
    public function filterRows($rows, $column_options)
    {

        $filtered = $rows->filter(
            function (&$val) use ($column_options) {
                foreach ($val as $row_key => $item) {
                    if (isset($column_options[$row_key]['filter_option'])
                        && isset($column_options[$row_key]['filter_value'])
                    ) {
                        $curr_filter
                            = $column_options[$row_key]['filter_option'];

                        $curr_filt_value
                            = $column_options[$row_key]['filter_value'];

                        $curr_filt_transf
                            = $column_options[$row_key]['filter_transform_value'];

                        if (isset($column_options[$row_key]['filter_parameters'])) {
                            $curr_filt_params
                                = $column_options[$row_key]['filter_parameters'];


                            if (in_array(
                                Dealer::REMOVE_SPACES,
                                $curr_filt_params
                            )
                            ) {
                                $item
                                    =
                                $val[$row_key] = str_replace(' ', '', $item);
                            }

                            if (in_array(
                                Dealer::REMOVE_COMMAS,
                                $curr_filt_params
                            )
                            ) {
                                $item
                                    =
                                $val[$row_key] = str_replace(',', '', $item);
                            }
                            if (in_array(
                                Dealer::REMOVE_DOT,
                                $curr_filt_params
                            )
                            ) {
                                $item
                                    =
                                $val[$row_key] = str_replace('.', '', $item);
                            }

                            if (in_array(
                                Dealer::CLEAR_RIGHT_OF_DOT,
                                $curr_filt_params
                            )
                            ) {
                                if (strpos($item, ".")) {
                                    $item
                                        = $val[$row_key]
                                        = substr($item, 0, strpos($item, ".")
                                    );
                                }
                            }

                            if (in_array(
                                Dealer::CLEAR_RIGHT_OF_COMMA,
                                $curr_filt_params
                            )
                            ) {
                                if (strpos($item, ",")) {
                                    $item
                                        = $val[$row_key]
                                        = substr($item, 0, strpos($item, ",")
                                    );
                                }
                            }
                        }


                        foreach (
                            $curr_filter as
                            $filter_key => $filter
                        ) {
                            switch ($filter) {
                                case "ignore_if_equal":
                                    if ($item
                                        == $curr_filt_value[$filter_key]
                                    ) {
                                        return false;
                                    }
                                    break;
                                case "upload_if_equal":
                                    if ($item
                                        == $curr_filt_value[$filter_key]
                                    ) {
                                        return true;
                                    }
                                    break;
                                case "is_equal_to":
                                    if (strpos($item,
                                        $curr_filt_value[$filter_key])
                                    ) {
                                        $val[$row_key]
                                            = str_replace($curr_filt_value[$filter_key],
                                            $curr_filt_transf[$filter_key],
                                            $item);
                                    }
                                    break;
                                default:
                                    return true;
                                    break;
                            }
                        }
                    }
                }

                return true;
            }
        );

        return $filtered;
    }

    /**
     * @param Collection $filtered
     * @param array      $column_options
     *
     * @return array|Collection
     */
    public function prepareFilteredRowsForInsert($filtered, $column_options)
    {
        $result_arr     = [];
        $manufacturers  = Manufacturer::all();
        $currency_types = Dealer::CURRENCY_TYPES;
        $now            = Carbon::now()->toDateTimeString();
        foreach ($filtered as $row) {
            $curr_row_set                    = [];
            $curr_row_set['manufacturer_id'] = $this->model->manufacturer_id;
            $curr_row_set['currency']        = $this->model->currency;
            $curr_row_set['prices']          = [];
            foreach ($row as $key => $item) {
                if ( ! is_null($column_options[$key]['name'])) {
                    switch ($column_options[$key]['name']) {
                        case 'manufacturer':
                            $manufacturer = $manufacturers->firstWhere(
                                'name',
                                $item
                            );

                            if (is_null($manufacturer)) {
                                if (empty($item)) {
                                    $curr_row_set['manufacturer_id'] = null;
                                    continue 2;
                                }
                                $manufacturer       = new Manufacturer();
                                $manufacturer->name = $item;
                                $manufacturer->save();
                                $manufacturers->push($manufacturer);
                            }
                            $curr_row_set['manufacturer_id']
                                = $manufacturer->id;


                            continue 2;
                            break;
                        case 'currency':
//                            if (!empty($item)) {
//                                $curr_row_set['currency'] = $item;
//                            }

                            $curr_row_set['currency'] = array_search(
                                $item,
                                $currency_types
                            );
                            continue 2;
                            break;
                        case 'price_for_amount':
                            $curr_row_set['prices'][0]['currency']
                                = Dealer::CURRENCY_TYPES[$this->model->currency];

                            $curr_row_set['prices'][0]['amount']
                                = $column_options[$key]['amount_for_price_value'];

                            $curr_row_set['prices'][0]['price']
                                = intval($item);
                            continue 2;
                            break;
                        case 'amount':
                        case 'additional_amount':
                            if (isset($curr_row_set['amount'])) {
                                $curr_row_set['amount'] += intval($item);
                            } else {
                                $curr_row_set['amount'] = intval($item);
                            }
                            continue 2;
                            break;
                        case 'price':
                            $curr_row_set['price'] = intval($item);
                            continue 2;
                            break;
                        case 'currency_for_price1':
                        case 'currency_for_price2':
                        case 'currency_for_price3':
                        case 'currency_for_price4':
                        case 'currency_for_price5':
                        case 'currency_for_price6':
                        case 'currency_for_price7':
                        case 'currency_for_price8':
                        case 'currency_for_price9':
                        case 'currency_for_price10':
                        case 'currency_for_price11':
                        case 'currency_for_price12':
                            if ( ! empty($item)) {
                                $curr_price_key = intval(
                                    str_replace(
                                        'currency_for_price',
                                        '',
                                        $column_options[$key]['name']
                                    )
                                );
                                $curr_row_set['prices'][$curr_price_key]['currency']
                                                = $item;
//                                $curr_row_set['prices'][$curr_price_key]['currency']
//                                    = array_search(
//                                    $item,
//                                    Dealer::CURRENCY_TYPES
//                                );
                            }

                            continue 2;
                            break;
                        case 'additional_price1':
                        case 'additional_price2':
                        case 'additional_price3':
                        case 'additional_price4':
                        case 'additional_price5':
                        case 'additional_price6':
                        case 'additional_price7':
                        case 'additional_price8':
                        case 'additional_price9':
                        case 'additional_price10':
                        case 'additional_price11':
                        case 'additional_price12':
                            if ( ! empty(intval($item))) {
                                $curr_price_key = intval(
                                    str_replace(
                                        'additional_price',
                                        '',
                                        $column_options[$key]['name']
                                    )
                                );
                                $curr_row_set['prices'][$curr_price_key]['price']
                                                = intval($item);
                            }
                            continue 2;
                            break;
                        case 'amount_for_price1':
                        case 'amount_for_price2':
                        case 'amount_for_price3':
                        case 'amount_for_price4':
                        case 'amount_for_price5':
                        case 'amount_for_price6':
                        case 'amount_for_price7':
                        case 'amount_for_price8':
                        case 'amount_for_price9':
                        case 'amount_for_price10':
                        case 'amount_for_price11':
                        case 'amount_for_price12':
                            if ( ! empty(intval($item))) {
                                $curr_price_key = intval(
                                    str_replace(
                                        'amount_for_price',
                                        '',
                                        $column_options[$key]['name']
                                    )
                                );
                                $curr_row_set['prices'][$curr_price_key]['amount']
                                                = intval($item);
                            }
                            continue 2;
                            break;
                        default:
                            $curr_row_set[$column_options[$key]['name']]
                                = $item;
                            continue 2;
                            break;
                    }

                }
            }

            foreach ($curr_row_set['prices'] as $key => $item) {
                if ( ! isset($item['price']) || empty($item['price'])) {
                    unset($curr_row_set['prices'][$key]);
                }
                if ( ! isset($item['currency']) || empty($item['currency'])) {
                    $curr_row_set['prices'][$key]['currency']
                        = Dealer::CURRENCY_TYPES[$this->model->currency];

                }

            }
            $curr_row_set['dealer_id']  = $this->model->id;
            $curr_row_set['currency']   = $this->model->currency;
            $curr_row_set['updated_at'] = $now;
            $curr_row_set['created_at'] = $now;
            $curr_row_set['prices']     = json_encode($curr_row_set['prices']);

            if (isset($curr_row_set['name'])) {
                $result_arr[] = $curr_row_set;
            }

        }

        $result_arr = collect($result_arr);

        return $result_arr;
    }
}
