<?php

namespace Supsign\Also;

class AlsoFTP
{

    public function __construct() {       
        $this->ftp = new \FtpClient\FtpClient();
        $this->ftp->connect(env('ALSO_FTP_HOST'));
        $this->ftp->login(env('ALSO_FTP_LOGIN'), env('ALSO_FTP_PASSWORD'));

        return $this;
    }

    protected function download($file)
    {
        file_put_contents(storage_path().'/'.$file, $this->ftp->getContent($file));

        return $this;
    }

    public function downloadAData()
    {
        return $this->download('');
    }

    public function downloadBData()
    {
        return $this->download('');
    }


    protected function importPrices()
    {

    }

    protected function importPrice()
    {

    }

    public function test() 
    {

    }
}
