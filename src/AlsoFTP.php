<?php

namespace Supsign\Also;

use Config;

class AlsoFTP
{
// Noch Auslagern in .env und Config
   


    public function __construct() {
      
           $host = 'ftpconnectch.also.com';
           $login = 'legedohifefidi';
           $password = 'kecezasazoti';
       
        $this->ftp = new \FtpClient\FtpClient();
        $this->ftp->connect($host);
        $this->ftp->login($login, $password);

        return $this;
    }



        public function setFile($file){
            return $this;
        }


    public function importPrices(){

    }

    protected function importPrice(){

    }




 
}
