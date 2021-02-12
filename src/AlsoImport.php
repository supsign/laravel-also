<?php

namespace Supsign\Also;

use App\CronTracker;
use App\Manufacturer;
use App\Price;
use App\Product;
use App\ProductSupplier;
use App\Vat;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use Supsign\LaravelCsvReader\CsvReader;
use ZipArchive;

class AlsoImport extends CsvReader
{
	protected 
		$downloadPath = 'imports/',
		$fieldAddresses = [
			'ProductID',
			'ManufacturerPartNumber',
			'AvailableQuantity',
			'NetPrice',
			'ManufacturerName',
			'MinimumOrderQuantity',
			'BasePriceQuantity',
			'ShippingLocation',
			'Bonus',
			'EuropeanArticleNumber',
			'Description',
			'NetRetailPrice',
			'CategoryText1',
			'CategoryText2',
			'CategoryText3',
			'EndOfLife',
			'GrossMass',
			'IsReturnable',
			'IsCancelable',
			'ShortDescription',
			'WarrantyText',
			'PlanningType',
			'FollowUpProduct',
			'CashDiscount',
			'ReplenishmentTime',
			'AvailableNextDate',
			'AvailableNextQuantity',
			'AvailabilityDate',
			'AvailabilityTime',
			'Status',
			'ProductStatus',
			'WarrantyID',
			'WarrantyMonths',
			'ProductType',
			'CNetImgageID',
			'CNetCategoryText1',
			'CNetCategoryText2',
			'CNetCategoryID',
			'CNetDataAvailable',
			'CategoryIDN',
			'CategoryID',
			'VatRate',
			'HierarchyID',
			'VatAmount',
			'PackageLength',
			'PackageWidth',
			'PackageHeight',
			'AbcIndicator',
			'CountryOfOrigin',
			'CommodityCode',
			'HierarchyText1',
			'HierarchyText2',
			'HierarchyText3',
			'UnitOfMass',
			'Currency',
			'UnitOfLength',
			'ManufacturerID',
			'NetPriceLastDay',
			'SalesPct2',
			'SalesRank2',
			'CustomerProductID',
			'SalesPct3',
			'SalesRank3',
			'Serialnumbers',
			'eClass',
			'DisChainStatus',
			'EsdProductID',
			'Assortment',
			'BasePriceQuantityUnit',
			'ProductLine',
			'PackagingUnit'
		],
		$lineDelimiter = ';',
		$logFile = 'AlsoLog.txt',
		$logPath = 'logs/',
		$manufacturerLog = [], 
		$soap = null,
		$sourceFile = '0010875889.csv',
		$downloadFile = 'pricelist-2.csv.zip',
		$tracker = null;

	public function __construct()
	{
		$this->tracker = CronTracker::firstOrCreate(['class' => static::class]);
	}

	protected function downloadFile()
	{
		$this->tracker->downloading();

		try {
		    (new AlsoFTP)
	        ->setLocalFile(Storage::path($this->downloadPath.$this->downloadFile))
	        ->setRemoteFile($this->downloadFile)
	        ->downloadFile();
		} catch (Exception $e) {
			$this->writeLog('Caught exception: '.$e->getMessage());
			$this->tracker->error()->stop();
		}

	    return $this->extractFile();
	}

	protected function extractFile()
	{
	    $this->tracker->extracting();

	    $zip = new ZipArchive;

	    if ($zip->open(Storage::path($this->downloadPath.$this->downloadFile))) {
			$zip->extractTo(Storage::path($this->downloadPath));
			$zip->close();
	    } else {
	    	throw new Exception('Failed to unzip '.$this->downloadFile, 1);
	    }

	    return $this;
	}

	protected function getManufacturer()
	{
		foreach (['also_name', 'name'] AS $manufacturerName) {
			$manufacturer = Manufacturer::where($manufacturerName, $this->line['ManufacturerName']);

			if ($manufacturer->count() === 1)
				return $manufacturer->first();
		}

		return null;
	}

	public function import()
	{
		if (!$this->tracker->readyToRun())
			return $this;

		$this->tracker->downloading();

		try {
			$this->downloadFile();
			$this->tracker->parsing();
			$this->importProducts();
		} catch (Exception $e) {
			$this->writeLog('Caught exception: '.$e->getMessage());
			$this->tracker->error()->stop();
			return $this;
		}

		$this->tracker->complete();

		return $this;
	}

	protected function importLine()
	{
		try {
			$this->tracker->progress();

			if (!$this->line['NetPrice'])
				return $this;

			$manufacturer = $this->getManufacturer();

			if (!$manufacturer) {
				$tmp = $this->logFile;
				$this->logFile = 'AlsoManufacturerLog.txt';

				if (!in_array($this->line['ManufacturerName'], $this->manufacturerLog)) {
					$this->manufacturerLog[] = $this->line['ManufacturerName'];
					$this->writeLog('Manufacturer "'.$this->line['ManufacturerName'].'" couldn\'t be matched');
				}
				
				$this->logFile = $tmp;
				return $this;
			}

			$product = Product::where([
				'manufacturer_id' => $manufacturer->id,
				'manufacturer_number' => $this->line['ManufacturerPartNumber']
			])->first();

			if (!$product)
				return $this;

			$productSupplier = ProductSupplier::firstOrNew([
				'product_id' => $product->id,
				'supplier_id' => 2
			]);

			$this->writeLog(($productSupplier->isClean() ? 'update' : 'create').' price of: "'.$product->id.' - '.$product->name);

			$productSupplier->supplier_product_id = $this->line['ProductID'];
			$productSupplier->last_seen = now();
			$productSupplier->save();

			$vat = Vat::where('rate', $this->line['VatRate'])->first();

			if (!$vat)
				throw new Exception('Tax Rate "'.$this->line['VatRate'].'"" not found', 1);

			if ($productSupplier->prices->last())
				if ($productSupplier->prices->last()->amount == $this->line['NetPrice'])
					return $this;

			$price = Price::create([
				'product_supplier_id' => $productSupplier->id,
				'amount' => $this->line['NetPrice'],
				'vat_id' => $vat->id,
			]);
		} catch (Exception $e) {
			$this->writeLog('Caught exception: '.$e->getMessage());
			$this->tracker->error();
		}

		return $this;
	}

	protected function importProducts()
	{
		$this
			->setDirectory(Storage::path($this->downloadPath))
			->setFileName($this->sourceFile)
			->readFiles();

		$this->tracker->setProgressTarget(count($this->lines))->importing();
		
		return parent::import();
	}

	protected function writeLog($data)
	{
		if (!is_array($data))
			$data = [$data];

		foreach ($data AS $line)
			$this->writeLogLine($line);

		return $this;
	}

	protected function writeLogLine(string $string)
	{	
		Storage::append($this->logPath.$this->logFile, '['.Carbon::now().'] '.$string);

		return $this;
	}
}
