<?php

namespace App\Http\Controllers\Admin\Dealers;

use App\Http\Controllers\Admin\Dealers\Repositories\DealerRepositoryInterface;
use App\Http\Controllers\Admin\Dealers\Requests\CreateDealerRequest;
use App\Http\Controllers\Admin\Dealers\Requests\UpdateDealerRequest;
use App\Http\Controllers\Admin\Dealers\Requests\UploadPriceFileRequest;
use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Manufacturer;
use App\Models\Provider;
use App\Models\XlsProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class DealerController extends Controller
{
    const PER_PAGE = 20;
    const COLUMN_OPTIONS
        = [
            //fields that are in DB
            'manufacturer' => 'Производитель',
            'name' => "Название",
            'article' => "Артикул ",
            'created_date' => "Год выпуска",
            'weight' => "Вес",
            'country' => "Страна производитель",
            'description' => "Описание",
            'min_order' => "Мин кол-во для заказа",
            'amount_in_pack' => "Кол-во в упаковке",
            'multiplicity' => 'Кратность',
            'amount' => "Кол-во на складе",
            'price' => "Цена за единицу",
            'currency' => "Валюта за единицу",
            'additional_amount' => "Суммировать к кол-ву на складе",
            'delivery_time' => "Срок поставки",
            'pdf_link' => "Ссылка на pdf",
            'rohc' => "ROHC",
            'provider_code' => "Код поставщика",
            'cover' => "Изображение товара",
            'package' => 'Вид единицы',

            'price_for_amount' => 'Цена за количество',
            //prices
            'currency_for_price1' => "Валюта для цены1",
            'currency_for_price2' => "Валюта для цены2",
            'currency_for_price3' => "Валюта для цены3",
            'currency_for_price4' => "Валюта для цены4",
            'currency_for_price5' => "Валюта для цены5",
            'currency_for_price6' => "Валюта для цены6",
            'currency_for_price7' => "Валюта для цены7",
            'currency_for_price8' => "Валюта для цены8",
            'currency_for_price9' => "Валюта для цены9",
            'currency_for_price10' => "Валюта для цены10",
            'currency_for_price11' => "Валюта для цены11",
            'currency_for_price12' => "Валюта для цены12",
            'additional_price1' => "Дополнительная цена1",
            'additional_price2' => "Дополнительная цена2",
            'additional_price3' => "Дополнительная цена3",
            'additional_price4' => "Дополнительная цена4",
            'additional_price5' => "Дополнительная цена5",
            'additional_price6' => "Дополнительная цена6",
            'additional_price7' => "Дополнительная цена7",
            'additional_price8' => "Дополнительная цена8",
            'additional_price9' => "Дополнительная цена9",
            'additional_price10' => "Дополнительная цена10",
            'additional_price11' => "Дополнительная цена11",
            'additional_price12' => "Дополнительная цена12",
            'amount_for_price1' => "Количество Для Цены1",
            'amount_for_price2' => "Количество Для Цены2",
            'amount_for_price3' => "Количество Для Цены3",
            'amount_for_price4' => "Количество Для Цены4",
            'amount_for_price5' => "Количество Для Цены5",
            'amount_for_price6' => "Количество Для Цены6",
            'amount_for_price7' => "Количество Для Цены7",
            'amount_for_price8' => "Количество Для Цены8",
            'amount_for_price9' => "Количество Для Цены9",
            'amount_for_price10' => "Количество Для Цены10",
            'amount_for_price11' => "Количество Для Цены11",
            'amount_for_price12' => "Количество Для Цены12",

            //unnknown
//            'price_for_amount' => "Цена для количества",
//            'amount_for_price_on_left' => "Кол-во для цены слева",
//            'amount_for_price_on_right' => "Кол-во для цены справа",
//            'filter_field' => "Поле для фильтрации"

        ];

    const CSV_SEPARATORS
        = [
            ';',
            ','
        ];


    const FOLDER = 'dealers';


    private $dealerRepo;


    public function __construct(
        DealerRepositoryInterface $dealerRepository
    ) {
        ini_set('max_execution_time', 6000);
        ini_set('memory_limit', '2048M');
        $this->dealerRepo = $dealerRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $dealers = Dealer::orderBy('created_at','desc')->paginate(self::PER_PAGE);

        return view('admin.dealers.index', compact('dealers'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        $providers = Provider::all();
        $manufacturers = Manufacturer::all();

        return view(
            'admin.dealers.create',
            compact('manufacturers', 'providers')
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateDealerRequest|Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(CreateDealerRequest $request)
    {
        $dealer = Dealer::create($request->all());
        return redirect()->route('admin.dealers.edit', $dealer)
            ->with('message', __('messages.successful_store'));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Dealer $dealer
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Dealer $dealer)
    {
        $providers = Provider::all();
        $manufacturers = Manufacturer::all();

        return view(
            'admin.dealers.edit',
            compact('manufacturers', 'providers', 'dealer')
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateDealerRequest|Request $request
     * @param  \App\Models\Dealer         $dealer
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDealerRequest $request, Dealer $dealer)
    {
        $dealer->update($request->all());
        return redirect()->route('admin.dealers.edit', $dealer)
            ->with('message', __('messages.successful_update'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Dealer $dealer
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Dealer $dealer)
    {
        try {
            $dealer->delete();
            request()->session()
                ->flash('message', __('messages.successful_delete'));

        } catch (\Exception $e) {
            request()
                ->session()
                ->flash(
                    'message',
                    __(
                        'messages.unsuccessful_delete',
                        ['message' => $e->getMessage()]
                    )
                );

        }

        return redirect()
            ->route('admin.dealers.index')
            ->with('message', __('messages.successful_delete'));
    }


    /**
     * @param Dealer $dealer
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function uploadPrice(Dealer $dealer)
    {
        $csv_separators = self::CSV_SEPARATORS;
        return view(
            'admin.dealers.upload-price',
            compact('dealer', 'csv_separators')
        );
    }

    /**
     * @param UploadPriceFileRequest|Request $request
     * @param Dealer                         $dealer
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function processPrice(
        UploadPriceFileRequest $request,
        Dealer $dealer
    ) {
        $validator = Validator::make(
            [
                'file' => $request->file('price_file'),
                'extension' => strtolower($request->file('price_file')
                    ->getClientOriginalExtension()),
            ],
            [
                'file' => 'required',
                'extension' => 'required|in:xlsx,xls,dbf,csv',
            ],
            [
                'in' => __('messages.parsing_prices_extension_error')
            ]
        );
        $validator->validate();
        $file = $request->file('price_file');

        $dealer->csv_separator = $request->input('csv_separator');
        $this->dealerRepo->setModel($dealer);
        $rows = [];
        if ($file) {
            $path = $file->storeAs(
                self::FOLDER . '/' . $dealer->dir,
                $file->getClientOriginalName()
            );
            $rows = $this->dealerRepo->processFile($path);
        }

        if (array_search(
                $file->getClientOriginalExtension(),
                Dealer::FILE_TYPES
            ) !== false
        ) {
            $dealer->file_type = array_search(
                $file->getClientOriginalExtension(),
                Dealer::FILE_TYPES
            );
        } else {
            $dealer->file_type = null;
        }

        $dealer->save();


        $column_options = self::COLUMN_OPTIONS;

        return view(
            'admin.dealers.process-price',
            compact(
                'dealer',
                'rows',
                'column_options',
                'path'
            )
        );
    }


    public function submitPrice(
        Request $request,
        Dealer $dealer
    ) {
        ini_set('max_execution_time', 6000);
        ini_set('memory_limit', '2000M');
        $column_options = $request->input('columns');
        $path = $request->input('file_path');

        $this->dealerRepo->setModel($dealer);

        $dealer->last_uploaded_file = $path;


        $rows = $this->dealerRepo->processFile($path);


        $filtered_rows = $this->dealerRepo->filterRows(
            $rows,
            $column_options
        );


        $rows_for_insert
            = $this->dealerRepo
            ->prepareFilteredRowsForInsert($filtered_rows, $column_options);


        $result_chunks = $rows_for_insert->chunk(200);

        $dealer->xlsProducts()->delete();

        foreach ($result_chunks as $chunk) {
            XlsProduct::insert($chunk->toArray());
        }
        $dealer->provider->touch();
        $dealer->column_options = $column_options;
        $dealer->last_upload = Carbon::now();
        $dealer->save();


        return redirect()->route('admin.dealers.index')
            ->with('message', __('messages.import_successful'));
    }

}
