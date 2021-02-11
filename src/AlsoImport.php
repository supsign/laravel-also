<?php

namespace Supsign\Also;

use App\CronTracker;
use App\Manufacturer;
use App\Product;
use App\ProductSupplier;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use Supsign\LaravelCsvReader\CsvReader;
use ZipArchive;

class AlsoImport extends CsvReader
{
	protected 
		$tmp = array(),
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
		$downloadPath = 'imports/',
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

	    (new AlsoFTP)
	        ->setLocalFile(Storage::path($this->downloadPath.$this->downloadFile))
	        ->setRemoteFile($this->downloadFile)
	        ->downloadFile();

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

	public function import()
	{
		$this->tracker->downloading();
		// try {
			// $this->downloadFile();
			$this->tracker->parsing();
			$this->importProducts();
		// } catch (Exception $e) {
		// 	$this->writeLog('Caught exception: '.$e->getMessage());
		// 	$this->tracker->error()->stop();
		// 	return $this;
		// }

		return $this;
	}

	protected function importLine()
	{
		if (!in_array($this->line['ManufacturerName'], $this->tmp))
			$this->tmp[] = $this->line['ManufacturerName'];
		else 
			return $this;

		$manufacturer = Manufacturer::firstOrNew([
			'name' => $this->line['ManufacturerName']
		]);

		if (!$manufacturer->isDirty()) {
			$manufacturer->also_name = $this->line['ManufacturerName'];
			$manufacturer->save();
		} else 
			return $this;

		$product = Product::where([
			'manufacturer_id' => $manufacturer->id,
			'manufacturer_number' => $this->line['ManufacturerPartNumber']
		])->first();

		if (!$product)
			return $this;

		$productSupplier = ProductSupplier::firstOrNew([
			'product_id' => $product->id,
			'supplier_product_id' => $this->line['ProductID'], 
			'supplier_id' => 2
		]);

		$productSupplier->last_seen = now();
		$productSupplier->save();

		return $this;
	}

	protected function importProducts()
	{
		$this
			->setDirectory(Storage::path($this->downloadPath))
			->setFileName($this->sourceFile);
		
		parent::import();
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
