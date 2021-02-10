<?php

namespace Supsign\Also;

use Supsign\LaravelFtpConnector\FtpConnector;

class AlsoFTP extends FtpConnector
{
    public function __construct() 
    {
        return parent::__construct('ALSO');
    }
}
