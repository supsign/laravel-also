<?php

namespace Supsign\Also;

use App\CronTracker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Supsign\LaravelCsvReader\CsvReader;
use ZipArchive;

class AlsoImport extends CsvReader
{
	protected 
		$logFile = 'AlsoLog.txt',
		$logPath = 'logs/',
		$downloadPath = 'imports/',
		$soap = null,
		$sourceFile = 'pricelist-2.csv.zip',
		$tracker = null;

	public function __construct()
	{
		$this->tracker = CronTracker::firstOrCreate(['class' => static::class]);
	}

	public function downloadFile()
	{
		$this->tracker->downloading();

	    (new AlsoFTP)
	        ->setLocalFile(Storage::path($this->downloadPath.$this->sourceFile))
	        ->setRemoteFile($this->sourceFile)
	        ->downloadFile();

	    return $this->extractFile();
	}

	public function extractFile()
	{
	    $this->tracker->extracting();

	    $zip = new ZipArchive;

	    if ($zip->open(Storage::path($this->downloadPath.$this->sourceFile))) {
			$zip->extractTo(Storage::path($this->downloadPath));
			$zip->close();
	    } else {
	    	throw new Exception('Failed to unzip '.$this->sourceFile, 1);
	    }

	    return $this;
	}

	public function import()
	{
		return $this->downloadFile();
	}

	public function writeLog($data)
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
