<?php

namespace App\Http\Controllers\Admin\Dealers\Repositories;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;


interface DealerRepositoryInterface extends BaseRepositoryInterface
{


    /**
     * @param string $path
     *
     * @return array|Collection
     */
    public function processFile($path);

    /**
     * @param $rows
     * @param $column_options
     *
     * @return mixed
     */
    public function filterRows($rows, $column_options);

    /**
     * @param Collection $filtered
     * @param array $column_options
     *
     * @return array|Collection
     */
    public function prepareFilteredRowsForInsert($filtered, $column_options);

}
