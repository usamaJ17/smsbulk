<?php

namespace App\Library;


class LiqPay
{


    protected $params = [];
    protected $_url;


    function __construct()
    {
        $this->_url = 'https://www.liqpay.ua/api/3/checkout';
        $this->param('version', '3');
        $this->param('action', 'pay');
    }

    public function param($param, $value): void
    {
        $this->params["$param"] = $value;
    }

    public function gw_submit(): void
    {
        ?>
        <html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo config('app.locale') ?>">

        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <title>Please wait while you're redirected</title>
            <meta name="csrf_token" content="{{ csrf_token() }}">
            <style>
                #redirect-container {
                    width: 410px;
                    margin: 130px auto 0;
                    background: #fff;
                    border: 1px solid #b5b5b5;
                    -moz-border-radius: 5px;
                    -webkit-border-radius: 5px;
                    border-radius: 5px;
                    text-align: center
                }

                #redirect-container h1 {
                    font-size: 22px;
                    color: #5f5f5f;
                    font-weight: normal;
                    margin: 22px 0 26px 0;
                    padding: 0
                }

                #redirect-container p {
                    font-size: 13px;
                    color: #454545;
                    margin: 0 0 12px 0;
                    padding: 0
                }

                #redirect-container img {
                    margin: 0 0 35px 0;
                    padding: 0
                }

            </style>
            <script type="text/javascript">
                function timedText() {
                    setTimeout('msg1()', 2000);
                    setTimeout('msg2()', 4000);
                    setTimeout('document.MetaRefreshForm.submit()', 4000);
                }

                function msg1() {
                    document.getElementById('redirect-message').firstChild.nodeValue = "Preparing Data...";
                }

                function msg2() {
                    document.getElementById('redirect-message').firstChild.nodeValue = "Redirecting...";
                }
            </script>
        </head>

        <body <?php echo " onLoad=\"document.forms['gw'].submit();\" "; ?> >
        <div id="redirect-container">
            <h1>Please wait while you&rsquo;re redirected</h1>
            <p class="redirect-message" id="redirect-message">Loading Data...</p>
            <script type="text/javascript">timedText()</script>
        </div>
        <form name="gw" action="<?php echo $this->_url; ?>" method="POST">
            <?php
            foreach ($this->params as $name => $value) {
                echo "<input type=\"hidden\" name=\"$name\" value=\"$value\"/>\n";
            }
            ?>
        </form>
        </body>
        </html>
    <?php }
}
